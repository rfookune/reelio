<?php

use App\Enums\TitleKind;
use App\Services\Catalog\Sources\CatalogSource;
use App\Services\Catalog\Sources\FakeCatalogSource;
use App\Services\Catalog\Sources\Listings\RuntimeBand;
use Inertia\Testing\AssertableInertia as Assert;

test('a runtime band reaches Movie Browse', function (string $band) {
    $source = new FakeCatalogSource(browsePages: [movieListings(1, 3)]);
    app()->instance(CatalogSource::class, $source);

    $this->get(route('search', ['runtime' => $band]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('tab', 'movie')
            ->where('runtimeBand', $band)
            ->where('sort', 'top_rated'));

    expect($source->browseCalls)->toBe([['kind' => TitleKind::Movie->value, 'page' => 1]])
        ->and($source->browseFilters[0]->runtimeBand)->toBe(RuntimeBand::from($band))
        ->and($source->browseFilters[0]->isNarrowed())->toBeTrue();
})->with([
    'under 90 minutes' => ['under_90'],
    '90 to 120 minutes' => ['90_to_120'],
    'over 120 minutes' => ['over_120'],
]);

test('a runtime band requested on TV Shows redirects to Movie Browse', function () {
    $source = new FakeCatalogSource(browsePages: [movieListings(1, 3)]);
    app()->instance(CatalogSource::class, $source);

    $this->get(route('search', ['tab' => 'tv', 'runtime' => '90_to_120']))
        ->assertRedirect(route('search', ['runtime' => '90_to_120']));

    $this->get(route('search', ['runtime' => '90_to_120']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('tab', 'movie')
            ->where('runtimeBand', '90_to_120'));

    expect($source->browseCalls)->toBe([['kind' => TitleKind::Movie->value, 'page' => 1]])
        ->and($source->browseFilters[0]->runtimeBand)->toBe(RuntimeBand::NinetyToOneTwenty);
});

test('an unknown runtime band is ignored', function (mixed $band) {
    $source = new FakeCatalogSource(browsePages: [movieListings(1, 3)]);
    app()->instance(CatalogSource::class, $source);

    $this->get(route('search', ['runtime' => $band]))
        ->assertInertia(fn (Assert $page) => $page->where('runtimeBand', null));

    expect($source->browseFilters[0]->runtimeBand)->toBeNull();
})->with([
    'unknown' => ['under_60'],
    'array' => [['under_90']],
]);

test('a Name search ignores a runtime band and keeps its requested tab', function () {
    $source = new FakeCatalogSource(searches: ['inter' => [movieListings(1, 3)]]);
    app()->instance(CatalogSource::class, $source);

    $this->get(route('search', ['q' => 'inter', 'tab' => 'tv', 'runtime' => 'over_120']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('tab', 'tv')
            ->where('runtimeBand', null));

    expect($source->searchCalls)->toBe([['query' => 'inter', 'page' => 1]])
        ->and($source->browseCalls)->toBe([]);
});

test('each runtime band has its own cached Browse page', function () {
    $source = new FakeCatalogSource(browsePages: [movieListings(1, 3)]);
    app()->instance(CatalogSource::class, $source);

    $this->get(route('search', ['runtime' => 'under_90']))->assertOk();
    $this->get(route('search', ['runtime' => 'over_120']))->assertOk();
    $this->get(route('search', ['runtime' => 'under_90']))->assertOk();

    expect($source->browseFilters)->toHaveCount(2)
        ->and($source->browseFilters[0]->runtimeBand)->toBe(RuntimeBand::UnderNinety)
        ->and($source->browseFilters[1]->runtimeBand)->toBe(RuntimeBand::OverOneTwenty);
});
