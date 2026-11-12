<?php

use App\Enums\TitleKind;
use App\Services\Catalog\Sources\CatalogSource;
use App\Services\Catalog\Sources\FakeCatalogSource;
use App\Services\Catalog\Sources\Listings\OriginalLanguage;
use Inertia\Testing\AssertableInertia as Assert;

const CURATED_LANGUAGES = [
    ['code' => 'en', 'name' => 'English'],
    ['code' => 'es', 'name' => 'Español'],
    ['code' => 'fr', 'name' => 'Français'],
    ['code' => 'de', 'name' => 'Deutsch'],
    ['code' => 'it', 'name' => 'Italiano'],
    ['code' => 'ja', 'name' => '日本語'],
    ['code' => 'ko', 'name' => '한국어'],
    ['code' => 'hi', 'name' => 'हिन्दी'],
    ['code' => 'zh', 'name' => '中文'],
    ['code' => 'pt', 'name' => 'Português'],
    ['code' => 'tr', 'name' => 'Türkçe'],
    ['code' => 'sv', 'name' => 'Svenska'],
];

/** Installs a fake source with a Browse page for each kind. */
function fakeLanguageBrowse(): FakeCatalogSource
{
    $source = new FakeCatalogSource(browsePages: [movieListings(1, 3)], tvBrowsePages: [[movieListing(51, kind: TitleKind::TvShow)]]);
    app()->instance(CatalogSource::class, $source);

    return $source;
}

test('the curated original languages are offered by native name on both tabs, with none chosen by default', function (string $tab) {
    fakeLanguageBrowse();

    $this->get(route('search', ['tab' => $tab]))->assertInertia(fn (Assert $page) => $page
        ->where('languages', CURATED_LANGUAGES)
        ->where('language', null));
})->with(['movie', 'tv']);

test('an original language reaches the source on each tab and makes Top rated the default sort', function (string $tab, TitleKind $kind) {
    $source = fakeLanguageBrowse();

    $this->get(route('search', ['tab' => $tab, 'language' => 'ko']))->assertInertia(fn (Assert $page) => $page
        ->where('tab', $tab)
        ->where('language', 'ko')
        ->where('sort', 'top_rated'));

    expect($source->browseCalls)->toBe([['kind' => $kind->value, 'page' => 1]])
        ->and($source->browseFilters[0]->originalLanguage)->toBe(OriginalLanguage::Korean)
        ->and($source->browseFilters[0]->isNarrowed())->toBeTrue();
})->with([
    'Movies' => ['movie', TitleKind::Movie],
    'TV Shows' => ['tv', TitleKind::TvShow],
]);

test('every curated language is accepted', function (string $code) {
    $source = fakeLanguageBrowse();

    $this->get(route('search', ['language' => $code]))->assertInertia(fn (Assert $page) => $page->where('language', $code));

    expect($source->browseFilters[0]->originalLanguage?->value)->toBe($code);
})->with(array_column(CURATED_LANGUAGES, 'code'));

test('a language outside the curated list or malformed is ignored and never reaches the source', function (mixed $language) {
    $source = fakeLanguageBrowse();

    $this->get(route('search', ['language' => $language]))->assertInertia(fn (Assert $page) => $page
        ->where('language', null)
        ->where('sort', 'popular')
        ->has('results.data', 3));

    expect($source->browseFilters[0]->originalLanguage)->toBeNull();
})->with([
    'a language not curated' => ['ru'],
    'upper case' => ['KO'],
    'a region' => ['zh-CN'],
    'a name' => ['korean'],
    'a list' => [['ko']],
    'empty' => [''],
]);

test('a search by Name takes no language', function () {
    $source = fakeLanguageBrowse();
    $source->searches = ['inter' => [movieListings(7, 8)]];

    $this->get(route('search', ['q' => 'inter', 'language' => 'ko']))->assertInertia(fn (Assert $page) => $page
        ->where('language', null)
        ->has('results.data', 2));

    expect($source->browseCalls)->toBe([]);
});

test('each language is its own cached Browse page', function () {
    $source = fakeLanguageBrowse();

    $this->get(route('search', ['language' => 'ko']))->assertOk();
    $this->get(route('search', ['language' => 'ko']))->assertOk();
    $this->get(route('search', ['language' => 'ja']))->assertOk();
    $this->get(route('search'))->assertOk();

    expect(array_map(fn ($filters) => $filters->originalLanguage?->value, $source->browseFilters))->toBe(['ko', 'ja', null]);
});
