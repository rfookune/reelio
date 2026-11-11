<?php

use App\Services\Catalog\Sources\CatalogSource;
use App\Services\Catalog\Sources\FakeCatalogSource;
use Inertia\Testing\AssertableInertia as Assert;

/** Installs a fake source with a Browse page and a Movie genre list. */
function fakeSortedBrowse(): FakeCatalogSource
{
    $source = new FakeCatalogSource(genres: [['id' => 35, 'name' => 'Comedy']], browsePages: [movieListings(1, 3)]);
    app()->instance(CatalogSource::class, $source);

    return $source;
}

/** @return list<string> the sort each Browse read asked for, in order */
function askedSorts(FakeCatalogSource $source): array
{
    return array_map(fn ($filters): string => $filters->sort()->value, $source->browseFilters);
}

test('unfiltered Browse is sorted by Popular and a filtered one by Top rated', function (array $query, string $sort) {
    $source = fakeSortedBrowse();

    $this->get(route('search', $query))->assertInertia(fn (Assert $page) => $page
        ->where('sort', $sort)
        ->where('explicitSort', null));

    expect(askedSorts($source))->toBe([$sort]);
})->with([
    'no filter' => [[], 'popular'],
    'a genre' => [['genre' => 35], 'top_rated'],
    'a genre the tab does not offer is no filter' => [['genre' => 999], 'popular'],
]);

test('an explicit sort wins over the default, with or without a filter', function (array $query, string $sort) {
    $source = fakeSortedBrowse();

    $this->get(route('search', $query))->assertInertia(fn (Assert $page) => $page
        ->where('sort', $sort)
        ->where('explicitSort', $sort));

    expect(askedSorts($source))->toBe([$sort]);
})->with([
    'Top rated, unfiltered' => [['sort' => 'top_rated'], 'top_rated'],
    'Popular, unfiltered' => [['sort' => 'popular'], 'popular'],
    'Popular, filtered' => [['sort' => 'popular', 'genre' => 35], 'popular'],
    'Top rated, filtered' => [['sort' => 'top_rated', 'genre' => 35], 'top_rated'],
]);

test('an unknown sort falls back to the default', function (mixed $sort, array $filter, string $default) {
    $source = fakeSortedBrowse();

    $this->get(route('search', ['sort' => $sort, ...$filter]))->assertInertia(fn (Assert $page) => $page
        ->where('sort', $default)
        ->where('explicitSort', null));

    expect(askedSorts($source))->toBe([$default]);
})->with([
    'unknown, unfiltered' => ['best', [], 'popular'],
    'unknown, filtered' => ['best', ['genre' => 35], 'top_rated'],
    'wrong case' => ['TOP_RATED', [], 'popular'],
    'a list' => [['top_rated'], [], 'popular'],
    'empty' => ['', [], 'popular'],
]);

test('each sort is its own cached Browse page', function () {
    $source = fakeSortedBrowse();

    $this->get(route('search', ['sort' => 'top_rated']))->assertOk();
    $this->get(route('search', ['sort' => 'top_rated']))->assertOk();
    $this->get(route('search', ['sort' => 'popular', 'genre' => 35]))->assertOk();
    $this->get(route('search'))->assertOk();

    expect(askedSorts($source))->toBe(['top_rated', 'popular', 'popular']);
});

test('a search by Name takes no sort', function () {
    $source = fakeSortedBrowse();
    $source->searches = ['inter' => [movieListings(7, 8)]];

    $this->get(route('search', ['q' => 'inter', 'sort' => 'top_rated']))->assertInertia(fn (Assert $page) => $page
        ->where('sort', 'popular')
        ->where('explicitSort', null));

    expect($source->browseCalls)->toBe([]);
});
