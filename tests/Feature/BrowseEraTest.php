<?php

use App\Enums\TitleKind;
use App\Services\Catalog\Sources\CatalogSource;
use App\Services\Catalog\Sources\FakeCatalogSource;
use App\Services\Catalog\Sources\Listings\Era;
use Inertia\Testing\AssertableInertia as Assert;

test('an era reaches Browse for each tab and counts as an active filter', function (string $tab, TitleKind $kind) {
    $source = new FakeCatalogSource(browsePages: [movieListings(1, 3)]);
    app()->instance(CatalogSource::class, $source);

    $this->get(route('search', ['tab' => $tab, 'era' => '1990s']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('tab', $tab)
            ->where('era', '1990s'));

    expect($source->browseCalls)->toBe([['kind' => $kind->value, 'page' => 1]])
        ->and($source->browseFilters[0]->era)->toBe(Era::NineteenNineties)
        ->and($source->browseFilters[0]->isNarrowed())->toBeTrue();
})->with([
    'Movies' => ['movie', TitleKind::Movie],
    'TV Shows' => ['tv', TitleKind::TvShow],
]);

test('an unknown era is ignored', function (mixed $value) {
    $source = new FakeCatalogSource(browsePages: [movieListings(1, 3)]);
    app()->instance(CatalogSource::class, $source);

    $this->get(route('search', ['era' => $value]))
        ->assertInertia(fn (Assert $page) => $page->where('era', null));

    expect($source->browseFilters[0]->era)->toBeNull();
})->with([
    'unknown' => ['1980s'],
    'array' => [['1990s']],
]);
