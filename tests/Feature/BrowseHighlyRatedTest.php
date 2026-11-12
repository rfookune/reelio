<?php

use App\Enums\TitleKind;
use App\Services\Catalog\Sources\CatalogSource;
use App\Services\Catalog\Sources\FakeCatalogSource;
use Inertia\Testing\AssertableInertia as Assert;

test('Highly rated reaches Browse for each tab and counts as an active filter', function (string $tab, TitleKind $kind) {
    $source = new FakeCatalogSource(browsePages: [movieListings(1, 3)]);
    app()->instance(CatalogSource::class, $source);

    $this->get(route('search', ['tab' => $tab, 'highly_rated' => '1']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('tab', $tab)
            ->where('highlyRated', true));

    expect($source->browseCalls)->toBe([['kind' => $kind->value, 'page' => 1]])
        ->and($source->browseFilters[0]->highlyRated)->toBeTrue()
        ->and($source->browseFilters[0]->isNarrowed())->toBeTrue();
})->with([
    'Movies' => ['movie', TitleKind::Movie],
    'TV Shows' => ['tv', TitleKind::TvShow],
]);

test('an unknown Highly rated value is ignored', function (mixed $value) {
    $source = new FakeCatalogSource(browsePages: [movieListings(1, 3)]);
    app()->instance(CatalogSource::class, $source);

    $this->get(route('search', ['highly_rated' => $value]))
        ->assertInertia(fn (Assert $page) => $page->where('highlyRated', false));

    expect($source->browseFilters[0]->highlyRated)->toBeFalse();
})->with([
    'false' => ['0'],
    'unknown' => ['yes'],
    'array' => [['1']],
]);
