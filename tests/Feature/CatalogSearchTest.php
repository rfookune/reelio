<?php

use App\Enums\TitleKind;
use App\Models\Movie;
use App\Services\Catalog\Enums\CatalogStatus;
use App\Services\Catalog\Search\CatalogSearch;
use App\Services\Catalog\Sources\CatalogSource;
use App\Services\Catalog\Sources\FakeCatalogSource;
use App\Services\Catalog\Sources\Listings\MovieListing;

/**
 * Installs a fake source that finds the given pages for "inter", and returns it.
 *
 * @param  list<list<MovieListing>>  $pages
 */
function fakeSearchSource(array $pages = [], bool $failing = false): FakeCatalogSource
{
    $source = new FakeCatalogSource(failing: $failing, searches: ['inter' => $pages]);
    app()->instance(CatalogSource::class, $source);

    return $source;
}

test('a search is read from the source once, then served from the cache for an hour', function () {
    $this->freezeTime();
    $source = fakeSearchSource([movieListings(1, 20), movieListings(21, 25)]);

    $first = app(CatalogSearch::class)->search('inter', 1);
    $this->travel(59)->minutes();
    $second = app(CatalogSearch::class)->search('inter', 1);

    expect($first->status)->toBe(CatalogStatus::Fresh)
        ->and(array_map(fn (MovieListing $listing): int => $listing->tmdbId, $first->value->listings))->toBe(range(1, 20))
        ->and([$first->value->totalPages, $first->value->totalResults])->toBe([2, 25])
        ->and($second->status)->toBe(CatalogStatus::Fresh)
        ->and($second->value)->toEqual($first->value)
        ->and($source->searchCalls)->toBe([['query' => 'inter', 'page' => 1]]);
});

test('each page of a search is cached on its own', function () {
    $source = fakeSearchSource([movieListings(1, 20), movieListings(21, 25)]);

    app(CatalogSearch::class)->search('inter', 1);
    $second = app(CatalogSearch::class)->search('inter', 2);

    expect(array_map(fn (MovieListing $listing): int => $listing->tmdbId, $second->value->listings))->toBe(range(21, 25))
        ->and($source->searchCalls)->toBe([['query' => 'inter', 'page' => 1], ['query' => 'inter', 'page' => 2]]);
});

test('an older search is read again, and served stale for up to a day while the source fails', function () {
    $this->freezeTime();
    fakeSearchSource([movieListings(1, 3)]);
    app(CatalogSearch::class)->search('inter', 1);

    $this->travel(61)->minutes();
    $refreshed = fakeSearchSource([movieListings(4, 6)]);
    $fresh = app(CatalogSearch::class)->search('inter', 1);

    $this->travel(24)->hours();
    fakeSearchSource(failing: true);
    $stale = app(CatalogSearch::class)->search('inter', 1);

    $this->travel(1)->second();
    $unavailable = app(CatalogSearch::class)->search('inter', 1);

    expect($refreshed->searchCalls)->toHaveCount(1)
        ->and(array_map(fn (MovieListing $listing): int => $listing->tmdbId, $fresh->value->listings))->toBe([4, 5, 6])
        ->and($stale->status)->toBe(CatalogStatus::Stale)
        ->and(array_map(fn (MovieListing $listing): int => $listing->tmdbId, $stale->value->listings))->toBe([4, 5, 6])
        ->and($unavailable->isUnavailable())->toBeTrue();
});

test('a search with nothing cached is unavailable while the source fails', function () {
    fakeSearchSource(failing: true);

    expect(app(CatalogSearch::class)->search('inter', 1)->isUnavailable())->toBeTrue();
});

test('each result\'s TMDB score is recorded, with or without a Movie row', function () {
    fakeSearchSource([[movieListing(1, voteAverage: 8.4, voteCount: 2500), movieListing(2, posterPath: null, voteAverage: 6.1, voteCount: 40), movieListing(3, voteCount: 0)]]);

    app(CatalogSearch::class)->search('inter', 1);

    $this->assertDatabaseHas('ratings', ['source' => 'tmdb', 'source_id' => 'movie:1', 'value' => 8.4, 'vote_count' => 2500]);
    $this->assertDatabaseHas('ratings', ['source' => 'tmdb', 'source_id' => 'movie:2', 'value' => 6.1, 'vote_count' => 40]);
    $this->assertDatabaseMissing('ratings', ['source' => 'tmdb', 'source_id' => 'movie:3']);
    expect(Movie::query()->count())->toBe(0);
});

test('a TV Show result\'s TMDB score is recorded under its own kind, apart from a Movie with the same catalog id', function () {
    fakeSearchSource([[movieListing(1, voteAverage: 8.4, voteCount: 2500), movieListing(1, kind: TitleKind::TvShow, voteAverage: 6.1, voteCount: 40)]]);

    app(CatalogSearch::class)->search('inter', 1);

    $this->assertDatabaseHas('ratings', ['source' => 'tmdb', 'source_id' => 'movie:1', 'value' => 8.4]);
    $this->assertDatabaseHas('ratings', ['source' => 'tmdb', 'source_id' => 'tv:1', 'value' => 6.1]);
});

test('a cached search keeps each result\'s kind, and is not shared between TV Shows on and off', function () {
    $source = fakeSearchSource([[movieListing(1), movieListing(1, kind: TitleKind::TvShow)]]);
    $search = app(CatalogSearch::class);

    $search->search('inter', 1);
    $cached = $search->search('inter', 1)->value->listings;

    expect($cached[1]->kind)->toBe(TitleKind::TvShow)->and($source->searchCalls)->toHaveCount(1);

    config(['catalog.tv_shows.enabled' => false]);

    expect($search->cachedSearch('inter', 1))->toBeNull();
    $search->search('inter', 1);
    expect($source->searchCalls)->toHaveCount(2);
});
