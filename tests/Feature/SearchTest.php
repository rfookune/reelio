<?php

use App\Jobs\RecordAnalyticsEvent;
use App\Models\Movie;
use App\Models\User;
use App\Models\UserMovie;
use App\Services\Catalog\Catalog;
use App\Services\Catalog\Enums\CatalogProfile;
use App\Services\Catalog\Sources\CatalogSource;
use App\Services\Catalog\Sources\FakeCatalogSource;
use App\Services\Catalog\Sources\MovieListing;
use App\Services\Ratings\Enums\RatingSourceName;
use App\Services\Ratings\RatingStore;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Installs a fake source whose stored Discover Trending is Movies 901–910, with the given search pages, and
 * returns it. A failing source fails every call, but nothing reads Trending from the source once it is stored.
 *
 * @param  array<string, list<list<MovieListing>>>  $searches
 */
function fakeSearchWithTrending(array $searches = [], bool $failingSearch = false): FakeCatalogSource
{
    fakeDiscoverSource(['trending' => [movieListings(901, 910)]]);
    app(Catalog::class)->buildDiscover(CatalogProfile::Background);
    $source = new FakeCatalogSource(failing: $failingSearch, searches: $searches);
    app()->instance(CatalogSource::class, $source);

    return $source;
}

/** @return array<string, mixed> the page object of a full visit */
function pageObject(TestResponse $response): array
{
    return $response->viewData('page');
}

test('the search page is a noindex page with one empty input and Trending from the stored Discover payload', function () {
    $source = fakeSearchWithTrending();

    $this->get(route('search'))
        ->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex')
        ->assertInertia(fn (Assert $page) => $page
            ->component('Search')
            ->where('query', '')
            ->where('status', null)
            ->where('results.data', [])
            ->where('trending', fn ($trending) => collect($trending)->pluck('tmdbId')->all() === range(901, 910)));

    expect($source->searchCalls)->toBe([])->and($source->sectionCalls)->toBe([]);
});

test('a query under two characters shows Trending without searching', function () {
    $source = fakeSearchWithTrending();

    $this->get(route('search', ['q' => ' i ']))->assertInertia(fn (Assert $page) => $page
        ->where('query', 'i')
        ->where('status', null)
        ->has('trending', 10));

    expect($source->searchCalls)->toBe([]);
});

test('without a stored Discover payload, Trending is empty and nothing is built', function () {
    $source = new FakeCatalogSource;
    app()->instance(CatalogSource::class, $source);

    $this->get(route('search'))->assertInertia(fn (Assert $page) => $page->where('trending', []));

    expect($source->sectionCalls)->toBe([]);
});

test('the query is trimmed and cut to 100 characters, and the page kept from 1 to 500', function (array $parameters, string $query, int $page) {
    $source = fakeSearchWithTrending();

    $this->get(route('search', $parameters))->assertOk()->assertInertia(fn (Assert $assert) => $assert->where('query', $query));

    expect($source->searchCalls)->toBe([['query' => $query, 'page' => $page]]);
})->with([
    'trimmed' => [['q' => '  inter  '], 'inter', 1],
    'cut to 100 characters' => [['q' => str_repeat('a', 99).'bc'], str_repeat('a', 99).'b', 1],
    'page below 1' => [['q' => 'inter', 'page' => '0'], 'inter', 1],
    'page above 500' => [['q' => 'inter', 'page' => '501'], 'inter', 500],
    'page not a number' => [['q' => 'inter', 'page' => 'two'], 'inter', 1],
    'Unicode whitespace' => [['q' => "\u{3000}inter\u{00A0}"], 'inter', 1],
]);

test('a query that isn\'t text is no query', function () {
    $source = fakeSearchWithTrending();

    $this->get('/search?q[]=inter')->assertOk()->assertInertia(fn (Assert $page) => $page->where('query', ''));

    expect($source->searchCalls)->toBe([]);
});

test('results are 20 a page in TMDB\'s order, poster-less Movies kept, each with its year, one rating and its link', function () {
    config(['ratings.sources.imdb.enabled' => true]);
    $stored = Movie::factory()->create(['tmdb_id' => 2, 'imdb_id' => 'tt0000002']);
    app(RatingStore::class)->record(RatingSourceName::Imdb, 'tt0000002', 6.2, 900);
    fakeSearchWithTrending(['inter' => [[
        movieListing(1, voteAverage: 7.5, voteCount: 1000),
        movieListing(2),
        movieListing(3, posterPath: null, releaseDate: null, voteCount: 0),
        ...movieListings(4, 20),
    ], movieListings(21, 25)]]);

    $response = $this->get(route('search', ['q' => 'inter']))->assertInertia(fn (Assert $page) => $page
        ->where('status', 'fresh')
        ->where('totalResults', 25)
        ->has('results.data', 20)
        ->where('results.data.0', [
            'tmdbId' => 1,
            'title' => 'Movie 1',
            'year' => 2026,
            'posterPath' => '/poster1.jpg',
            'ratings' => [['source' => 'tmdb', 'name' => 'TMDB', 'value' => 7.5, 'voteCount' => 1000]],
            'href' => route('movies.entry', ['tmdbId' => 1, 'referrer' => 'search']),
            'page' => 1,
        ])
        ->where('results.data.1.ratings.0.source', 'imdb')
        ->where('results.data.1.href', $stored->canonicalUrl('search'))
        ->where('results.data.2.posterPath', null)
        ->where('results.data.2.year', null)
        ->where('results.data.2.ratings', [])
        ->where('trending', []));

    expect(pageObject($response)['scrollProps']['results'])->toMatchArray(['pageName' => 'page', 'previousPage' => null, 'nextPage' => 2, 'currentPage' => 1])
        ->and(pageObject($response)['matchPropsOn'])->toContain('results.data.tmdbId');
});

test('the next page stops at the search\'s last page and at TMDB\'s page 500', function (int $pages, int $page, ?int $nextPage) {
    fakeSearchWithTrending(['inter' => array_fill(0, $pages, [movieListing(1)])]);

    $response = $this->get(route('search', ['q' => 'inter', 'page' => $page]));

    expect(pageObject($response)['scrollProps']['results'])->toMatchArray(['previousPage' => $page - 1 ?: null, 'nextPage' => $nextPage, 'currentPage' => $page]);
})->with([
    'more pages' => [3, 2, 3],
    'the last page' => [3, 3, null],
    'TMDB\'s page 500' => [501, 500, null],
]);

test('a later page the source can\'t answer points at itself, so "Show more" asks for it again', function () {
    fakeSearchWithTrending(failingSearch: true);

    $response = $this->get(route('search', ['q' => 'inter', 'page' => 3]));

    expect(pageObject($response)['scrollProps']['results'])->toMatchArray(['previousPage' => 3, 'nextPage' => 3, 'currentPage' => 3]);
});

test('no results shows Trending below the empty results', function () {
    fakeSearchWithTrending(['zzzz' => [[]]]);

    $this->get(route('search', ['q' => 'zzzz']))->assertInertia(fn (Assert $page) => $page
        ->where('status', 'fresh')
        ->where('totalResults', 0)
        ->where('results.data', [])
        ->has('trending', 10));
});

test('a search the source can\'t answer is unavailable, keeping the query and showing Trending', function () {
    fakeSearchWithTrending(failingSearch: true);

    $this->get(route('search', ['q' => 'inter']))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('query', 'inter')
        ->where('status', 'unavailable')
        ->where('totalResults', null)
        ->where('results.data', [])
        ->has('trending', 10));
});

test('a stale search is shown as it was', function () {
    $this->freezeTime();
    fakeSearchWithTrending(['inter' => [movieListings(1, 3)]]);
    app(Catalog::class)->search('inter', 1);
    $this->travel(2)->hours();
    fakeSearchWithTrending(failingSearch: true);

    $this->get(route('search', ['q' => 'inter']))->assertInertia(fn (Assert $page) => $page
        ->where('status', 'stale')
        ->has('results.data', 3)
        ->where('trending', []));
});

test('userMovies covers every result and Trending Movie, and merges as pages load', function () {
    $facts = UserMovie::factory()->for(Movie::factory()->state(['tmdb_id' => 2]))->create();
    fakeSearchWithTrending(['inter' => [movieListings(1, 20), movieListings(21, 22)]]);

    $response = $this->actingAs($facts->user)->get(route('search', ['q' => 'inter']))->assertInertia(fn (Assert $page) => $page
        ->where('userMovies.2', ['watchlisted' => true, 'watched' => false, 'watchedDate' => null])
        ->where('userMovies.20', ['watchlisted' => false, 'watched' => false, 'watchedDate' => null])
        ->missing('userMovies.21'));

    expect(pageObject($response)['mergeProps'])->toContain('userMovies');
});

test('each signed-in page-1 search records its query length and result count, never its text', function () {
    Queue::fake([RecordAnalyticsEvent::class]);
    $user = User::factory()->create();
    fakeSearchWithTrending(['interstellar' => [movieListings(1, 20), movieListings(21, 25)]]);

    $this->actingAs($user)->get(route('search', ['q' => 'interstellar']))->assertOk();
    $this->actingAs($user)->get(route('search', ['q' => 'interstellar', 'page' => 2]))->assertOk();

    Queue::assertPushed(RecordAnalyticsEvent::class, fn (RecordAnalyticsEvent $job) => $job->userId === $user->id
        && $job->name === 'movie_searched'
        && $job->properties === ['query_length' => 12, 'result_count' => 25]);
    Queue::assertPushed(RecordAnalyticsEvent::class, 1);
});

test('no search is recorded without a search, when it fails, or when only userMovies is reloaded', function () {
    Queue::fake([RecordAnalyticsEvent::class]);
    $user = User::factory()->create();
    fakeSearchWithTrending(['inter' => [movieListings(1, 3)]]);

    $this->actingAs($user)->get(route('search', ['q' => 'i']))->assertOk();
    $this->actingAs($user)->get(route('search', ['q' => 'inter']))->assertInertia(fn (Assert $page) => $page
        ->reloadOnly('userMovies', fn (Assert $reload) => $reload->has('userMovies.1')->missing('results')));
    Queue::assertPushed(RecordAnalyticsEvent::class, 1);

    fakeSearchWithTrending(failingSearch: true);
    $this->actingAs($user)->get(route('search', ['q' => 'other']))->assertOk();
    Queue::assertPushed(RecordAnalyticsEvent::class, 1);
});

test('reloading userMovies alone, as after a Watchlist action, reads the cache only and isn\'t limited', function () {
    $facts = UserMovie::factory()->for(Movie::factory()->state(['tmdb_id' => 2]))->create();
    $source = fakeSearchWithTrending(['inter' => [movieListings(1, 3)]]);
    $this->actingAs($facts->user)->freezeTime();
    $version = (string) $this->get(route('search', ['q' => 'inter']))->viewData('page')['version'];
    $partial = ['X-Inertia' => 'true', 'X-Inertia-Version' => $version, 'X-Inertia-Partial-Component' => 'Search', 'X-Inertia-Partial-Data' => 'userMovies'];

    for ($attempt = 1; $attempt <= 59; $attempt++) {
        $this->get(route('search', ['q' => 'interstellar']))->assertOk();
    }

    $this->get(route('search', ['q' => 'interstellar']))->assertTooManyRequests();
    $this->get(route('search', ['q' => 'inter']), $partial)->assertOk()->assertJsonPath('props.userMovies.2.watchlisted', true);
    $this->get(route('search', ['q' => 'never searched']), $partial)->assertOk();
    expect(collect($source->searchCalls)->pluck('query')->unique()->values()->all())->toBe(['inter', 'interstellar']);
});

test('search is limited to 60 requests a minute per user or IP', function () {
    fakeSearchWithTrending();
    $this->freezeTime();

    for ($attempt = 1; $attempt <= 60; $attempt++) {
        $this->get(route('search'))->assertOk();
    }

    $this->get(route('search'))->assertTooManyRequests();
    $this->actingAs(User::factory()->create())->get(route('search'))->assertOk();
});

test('each result says which page of the search it is on, so its IMDb rating can be asked for by that page', function () {
    fakeSearchWithTrending(['inter' => [[movieListing(1)], [movieListing(2)]]]);

    $this->get(route('search', ['q' => 'inter', 'page' => 2]))->assertInertia(fn (Assert $page) => $page
        ->where('results.data.0.tmdbId', 2)
        ->where('results.data.0.page', 2));
});

test('the page says whether the IMDb ratings of its results are looked up, which is not while IMDb is switched off', function (bool $enabled) {
    config(['ratings.sources.imdb.enabled' => $enabled]);
    fakeSearchWithTrending();

    $this->get(route('search'))->assertInertia(fn (Assert $page) => $page->where('looksUpImdbRatings', $enabled));
})->with([true, false]);
