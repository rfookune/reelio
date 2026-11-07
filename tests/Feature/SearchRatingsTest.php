<?php

use App\Jobs\RecordAnalyticsEvent;
use App\Models\Movie;
use App\Models\User;
use App\Services\Catalog\Catalog;
use App\Services\Catalog\Sources\CatalogSource;
use App\Services\Catalog\Sources\FakeCatalogSource;
use App\Services\Catalog\Sources\MovieListing;
use App\Services\Ratings\Enums\RatingSourceName;
use App\Services\Ratings\RatingStore;
use Illuminate\Support\Facades\Queue;

/**
 * Installs a fake source with the given search pages and IMDb ids, and searches once, as the Search page does,
 * so the page is cached for the ratings request that follows.
 *
 * @param  array<string, list<list<MovieListing>>>  $searches
 * @param  array<int, string|null>  $imdbIds
 */
function searchedWithImdbIds(array $searches, array $imdbIds, string $query = 'inter'): FakeCatalogSource
{
    config(['ratings.sources.imdb.enabled' => true]);
    $source = new FakeCatalogSource(searches: $searches, imdbIds: $imdbIds);
    app()->instance(CatalogSource::class, $source);
    app(Catalog::class)->search($query, 1);

    return $source;
}

test('the IMDb ratings of the Movies with no known IMDb id are looked up and returned by catalog id', function () {
    $source = searchedWithImdbIds(['inter' => [movieListings(1, 4)]], [1 => 'tt0000001', 2 => 'tt0000002', 3 => null]);
    Movie::factory()->create(['tmdb_id' => 4, 'imdb_id' => 'tt0000004']);
    app(RatingStore::class)->record(RatingSourceName::Imdb, 'tt0000001', 8.1, 5000);
    app(RatingStore::class)->record(RatingSourceName::Tmdb, '1', 7.5, 1000);
    app(RatingStore::class)->record(RatingSourceName::Imdb, 'tt0000004', 6.0, 100);

    $this->getJson(route('search.ratings', ['q' => 'inter']))->assertOk()->assertExactJson(['ratings' => [
        '1' => [
            ['source' => 'imdb', 'name' => 'IMDb', 'value' => 8.1, 'voteCount' => 5000],
            ['source' => 'tmdb', 'name' => 'TMDB', 'value' => 7.5, 'voteCount' => 1000],
        ],
    ]]);

    expect(array_column($source->imdbIdCalls, 'tmdbId'))->toBe([1, 2, 3]);
});

test('a second request, and the Search page, use the remembered ids and ask the source for nothing', function () {
    $source = searchedWithImdbIds(['inter' => [movieListings(1, 2)]], [1 => 'tt0000001']);
    app(RatingStore::class)->record(RatingSourceName::Imdb, 'tt0000001', 8.1, 5000);
    $this->getJson(route('search.ratings', ['q' => 'inter']))->assertOk();

    $this->getJson(route('search.ratings', ['q' => 'inter']))->assertJsonPath('ratings.1.0.source', 'imdb');
    $this->get(route('search', ['q' => 'inter']))->assertInertia(fn ($page) => $page->where('results.data.0.ratings.0.source', 'imdb'));

    expect($source->imdbIdCalls)->toHaveCount(2);
});

test('only a page already searched is read: a page not in the cache looks up nothing and searches nothing', function () {
    $source = searchedWithImdbIds(['inter' => [movieListings(1, 2), movieListings(3, 4)]], [1 => 'tt0000001']);
    app(RatingStore::class)->record(RatingSourceName::Imdb, 'tt0000001', 8.1, 5000);

    $this->getJson(route('search.ratings', ['q' => 'inter', 'page' => 2]))->assertOk()->assertExactJson(['ratings' => []]);
    $this->getJson(route('search.ratings', ['q' => 'never searched']))->assertOk()->assertExactJson(['ratings' => []]);

    expect($source->imdbIdCalls)->toBe([])->and($source->searchCalls)->toHaveCount(1);
});

test('the query is read as the Search page reads it, and no other input is', function (mixed $query, int $imdbCalls) {
    $source = searchedWithImdbIds(['inter' => [movieListings(1, 1)]], [1 => 'tt0000001']);

    $this->getJson(route('search.ratings', ['q' => $query, 'ids' => [1, 2, 3]]))->assertOk();

    expect($source->imdbIdCalls)->toHaveCount($imdbCalls);
})->with([
    'padded' => ['  inter  ', 1],
    'too short' => ['i', 0],
    'an array' => [['inter'], 0],
    'another query' => ['interstellar', 0],
]);

test('with IMDb switched off, nothing is looked up', function () {
    $source = searchedWithImdbIds(['inter' => [movieListings(1, 2)]], [1 => 'tt0000001']);
    config(['ratings.sources.imdb.enabled' => false]);

    $this->getJson(route('search.ratings', ['q' => 'inter']))->assertOk()->assertExactJson(['ratings' => []]);

    expect($source->imdbIdCalls)->toBe([]);
});

test('a failing source leaves the ratings empty, and the next request tries again', function () {
    $source = searchedWithImdbIds(['inter' => [movieListings(1, 2)]], [1 => 'tt0000001']);
    $source->failing = true;
    app(RatingStore::class)->record(RatingSourceName::Imdb, 'tt0000001', 8.1, 5000);

    $this->getJson(route('search.ratings', ['q' => 'inter']))->assertOk()->assertExactJson(['ratings' => []]);

    $source->failing = false;
    $this->getJson(route('search.ratings', ['q' => 'inter']))->assertJsonPath('ratings.1.0.source', 'imdb');
});

test('the response is noindex and records no analytics event', function () {
    searchedWithImdbIds(['inter' => [movieListings(1, 1)]], [1 => 'tt0000001']);
    Queue::fake([RecordAnalyticsEvent::class]);

    $this->actingAs(User::factory()->create())->getJson(route('search.ratings', ['q' => 'inter']))
        ->assertOk()->assertHeader('X-Robots-Tag', 'noindex');

    Queue::assertNothingPushed();
});

test('the ratings requests have their own limit, apart from Search\'s', function () {
    searchedWithImdbIds(['inter' => [movieListings(1, 1)]], [1 => 'tt0000001']);

    foreach (range(1, 60) as $ignored) {
        $this->getJson(route('search.ratings', ['q' => 'inter']))->assertOk();
    }

    $this->getJson(route('search.ratings', ['q' => 'inter']))->assertTooManyRequests();
    $this->get(route('search', ['q' => 'inter']))->assertOk();
});
