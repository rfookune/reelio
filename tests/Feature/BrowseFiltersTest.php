<?php

use App\Enums\TitleKind;
use App\Models\Movie;
use App\Models\UserMovie;
use App\Services\Catalog\Sources\CatalogSource;
use App\Services\Catalog\Sources\FakeCatalogSource;
use Inertia\Testing\AssertableInertia as Assert;

const MOVIE_GENRES = [['id' => 28, 'name' => 'Action'], ['id' => 35, 'name' => 'Comedy'], ['id' => 878, 'name' => 'Science Fiction']];
const TV_GENRES = [['id' => 9035, 'name' => 'Comedy'], ['id' => 10759, 'name' => 'Action & Adventure']];

/** Installs a fake source with Browse pages for each kind and a genre list for each kind. */
function fakeFilteredBrowse(bool $failing = false, array $movieGenres = MOVIE_GENRES, array $tvGenres = TV_GENRES): FakeCatalogSource
{
    $source = new FakeCatalogSource(
        failing: $failing,
        genres: $movieGenres,
        browsePages: [movieListings(1, 3)],
        tvBrowsePages: [[movieListing(51, kind: TitleKind::TvShow)]],
        tvGenres: $tvGenres,
    );
    app()->instance(CatalogSource::class, $source);

    return $source;
}

/** @return list<int|null> the genre each Browse read asked for, in order */
function askedGenres(FakeCatalogSource $source): array
{
    return array_map(fn ($filters): ?int => $filters->genreId, $source->browseFilters);
}

test('a genre reaches the source for the tab it is chosen on', function (string $tab, int $genreId, string $kind) {
    $source = fakeFilteredBrowse();

    $this->get(route('search', ['tab' => $tab, 'genre' => $genreId]))->assertInertia(fn (Assert $page) => $page
        ->where('tab', $tab)
        ->where('genre', $genreId));

    expect(askedGenres($source))->toBe([$genreId])
        ->and($source->browseCalls)->toBe([['kind' => $kind, 'page' => 1]]);
})->with([
    'a Movie genre' => ['movie', 35, 'movie'],
    'a TV genre' => ['tv', 10759, 'tv'],
]);

test('the genres offered are those of the tab, from the source, with no genre chosen by default', function (string $tab, array $expected) {
    fakeFilteredBrowse();

    $this->get(route('search', ['tab' => $tab]))->assertInertia(fn (Assert $page) => $page
        ->where('genres', $expected)
        ->where('genre', null));
})->with([
    'Movies' => ['movie', MOVIE_GENRES],
    'TV Shows' => ['tv', TV_GENRES],
]);

test('browsing without a genre asks the source for no genre', function () {
    $source = fakeFilteredBrowse();

    $this->get(route('search'))->assertOk();

    expect(askedGenres($source))->toBe([null]);
});

test('switching tabs keeps the genre when the other tab has one of the same name, and clears it when it has none', function (string $tab, int $genreId, ?int $kept) {
    $source = fakeFilteredBrowse();

    $this->get(route('search', ['tab' => $tab, 'genre' => $genreId]))->assertInertia(fn (Assert $page) => $page->where('genre', $kept));

    expect(askedGenres($source))->toBe([$kept]);
})->with([
    'Comedy from Movies to TV Shows' => ['tv', 35, 9035],
    'Comedy from TV Shows to Movies' => ['movie', 9035, 35],
    'Action on TV Shows is Action & Adventure, a different name' => ['tv', 28, null],
    'Science Fiction has no TV genre of that name' => ['tv', 878, null],
    'Action & Adventure has no Movie genre of that name' => ['movie', 10759, null],
]);

test('an invalid genre is ignored and never reaches the source', function (mixed $genre) {
    $source = fakeFilteredBrowse();

    $this->get(route('search', ['genre' => $genre]))->assertInertia(fn (Assert $page) => $page
        ->where('genre', null)
        ->has('results.data', 3));

    expect(askedGenres($source))->toBe([null]);
})->with([
    'unknown id' => ['999999'],
    'not a number' => ['comedy'],
    'negative' => ['-35'],
    'signed' => ['+35'],
    'decimal' => ['35.5'],
    'a list' => [['35']],
    'empty' => [''],
    'huge' => ['99999999999999999999'],
]);

test('a genre is ignored while the genre list is unavailable, and the page still browses', function () {
    $source = fakeFilteredBrowse(movieGenres: []);

    $this->get(route('search', ['genre' => 35]))->assertInertia(fn (Assert $page) => $page
        ->where('genres', [])
        ->where('genre', null)
        ->has('results.data', 3));

    expect(askedGenres($source))->toBe([null]);
});

test('a request with a query and a genre is a search by Name, which ignores the filters', function () {
    $source = fakeFilteredBrowse();
    $source->searches = ['inter' => [movieListings(7, 8)]];

    $this->get(route('search', ['q' => 'inter', 'genre' => 35, 'tab' => 'tv']))->assertInertia(fn (Assert $page) => $page
        ->where('genre', null)
        ->has('results.data', 2));

    expect($source->browseCalls)->toBe([])->and($source->searchCalls)->toBe([['query' => 'inter', 'page' => 1]]);
});

test('each genre is its own cached Browse page, and the genre lists are cached', function () {
    $source = fakeFilteredBrowse();

    $this->get(route('search', ['genre' => 35]))->assertOk();
    $this->get(route('search', ['genre' => 35]))->assertOk();
    $this->get(route('search', ['genre' => 28]))->assertOk();
    $this->get(route('search'))->assertOk();

    expect(askedGenres($source))->toBe([35, 28, null])
        ->and($source->genreCalls)->toHaveCount(1);
});

test('a Browse page the source can\'t answer is unavailable with a genre chosen', function () {
    fakeFilteredBrowse(failing: true);

    $this->get(route('search', ['genre' => 35]))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('status', 'unavailable')
        ->where('results.data', []));
});

test('reloading userMovies alone reads the genre lists from the cache only', function () {
    $facts = UserMovie::factory()->for(Movie::factory()->state(['tmdb_id' => 2]))->create();
    $source = fakeFilteredBrowse();
    $this->actingAs($facts->user)->freezeTime();
    $version = (string) $this->get(route('search', ['genre' => 35]))->viewData('page')['version'];
    $genreReads = count($source->genreCalls);
    $partial = ['X-Inertia' => 'true', 'X-Inertia-Version' => $version, 'X-Inertia-Partial-Component' => 'Search', 'X-Inertia-Partial-Data' => 'userMovies'];

    $this->get(route('search', ['genre' => 35]), $partial)->assertOk()->assertJsonPath('props.userMovies.2.watchlisted', true);

    expect(count($source->genreCalls))->toBe($genreReads);
});

test('the genre options of the tab are still offered while searching by Name, so choosing one can leave the search', function (string $tab, array $expected) {
    $source = fakeFilteredBrowse();
    $source->searches = ['inter' => [movieListings(7, 8)]];

    $this->get(route('search', ['q' => 'inter', 'tab' => $tab]))->assertInertia(fn (Assert $page) => $page
        ->where('genres', $expected)
        ->where('genre', null));
})->with([
    'Movies' => ['movie', MOVIE_GENRES],
    'TV Shows' => ['tv', TV_GENRES],
]);

test('a genre of the other kind carries over by its matching name', function () {
    $source = fakeFilteredBrowse();

    $this->get(route('search', ['genre' => 9035]))->assertInertia(fn (Assert $page) => $page->where('genre', 35));

    expect(askedGenres($source))->toBe([35]);
});
