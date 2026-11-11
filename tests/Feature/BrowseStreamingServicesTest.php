<?php

use App\Services\Catalog\Sources\CatalogSource;
use App\Services\Catalog\Sources\FakeCatalogSource;
use Inertia\Testing\AssertableInertia as Assert;

const US_MOVIE_SERVICES = [['id' => 8, 'name' => 'Netflix'], ['id' => 9, 'name' => 'Prime Video'], ['id' => 384, 'name' => 'Max']];
const CA_MOVIE_SERVICES = [['id' => 8, 'name' => 'Netflix'], ['id' => 230, 'name' => 'Crave']];
const US_TV_SERVICES = [['id' => 8, 'name' => 'Netflix'], ['id' => 15, 'name' => 'Hulu']];

/** Installs a fake source with a Browse page and Streaming services for each kind, by country. */
function fakeServiceBrowse(bool $withServices = true): FakeCatalogSource
{
    $source = new FakeCatalogSource(
        genres: [['id' => 35, 'name' => 'Comedy']],
        browsePages: [movieListings(1, 3)],
        streamingServices: $withServices ? ['US' => US_MOVIE_SERVICES, 'CA' => CA_MOVIE_SERVICES] : [],
        tvStreamingServices: $withServices ? ['US' => US_TV_SERVICES] : [],
    );
    app()->instance(CatalogSource::class, $source);

    return $source;
}

/** @return list<array{list<int>, ?string}> the Streaming services and country each Browse read asked for, in order */
function askedServices(FakeCatalogSource $source): array
{
    return array_map(fn ($filters): array => [$filters->streamingServiceIds, $filters->country], $source->browseFilters);
}

test('the Streaming services offered are those of the visitor\'s country and the tab\'s kind', function (string $tab, array $expected) {
    fakeServiceBrowse();

    $this->get(route('search', ['tab' => $tab]))->assertInertia(fn (Assert $page) => $page
        ->where('streamingServices', $expected)
        ->where('selectedStreamingServices', []));
})->with([
    'Movies' => ['movie', US_MOVIE_SERVICES],
    'TV Shows' => ['tv', US_TV_SERVICES],
]);

test('the country follows the chosen country, else the detected one, else the default', function (array $cookies, array $headers, string $country, array $expected) {
    $source = fakeServiceBrowse();

    $this->withCookies($cookies)->withHeaders($headers)->get(route('search', ['services' => '8']))->assertInertia(fn (Assert $page) => $page
        ->where('streamingServices', $expected)
        ->where('selectedStreamingServices', [8]));

    expect(askedServices($source))->toBe([[[8], $country]]);
})->with([
    'the default' => [[], [], 'US', US_MOVIE_SERVICES],
    'a chosen country' => [['country' => 'CA'], ['CF-IPCountry' => 'US'], 'CA', CA_MOVIE_SERVICES],
    'a detected country' => [[], ['CF-IPCountry' => 'CA'], 'CA', CA_MOVIE_SERVICES],
]);

test('several services reach the source together, and none is asked for none', function () {
    $source = fakeServiceBrowse();

    $this->get(route('search', ['services' => '384,8']))->assertInertia(fn (Assert $page) => $page->where('selectedStreamingServices', [8, 384]));
    $this->get(route('search'))->assertOk();

    expect(askedServices($source))->toBe([[[8, 384], 'US'], [[], 'US']]);
});

test('a service the country does not offer for the tab, or a malformed value, is ignored and never reaches the source', function (string $services, array $kept) {
    $source = fakeServiceBrowse();

    $this->get(route('search', ['services' => $services]))->assertInertia(fn (Assert $page) => $page->where('selectedStreamingServices', $kept));

    expect(askedServices($source))->toBe([[$kept, 'US']]);
})->with([
    'offered in another country only' => ['230', []],
    'offered on the other tab only' => ['15', []],
    'unknown' => ['999999', []],
    'not a number' => ['netflix', []],
    'negative' => ['-8', []],
    'signed' => ['+8', []],
    'decimal' => ['8.5', []],
    'huge' => ['99999999999999999999', []],
    'empty' => ['', []],
    'valid among invalid' => ['x,8,,230,8', [8]],
]);

test('a list given as an array is ignored', function () {
    $source = fakeServiceBrowse();

    $this->get(route('search', ['services' => ['8']]))->assertInertia(fn (Assert $page) => $page->where('selectedStreamingServices', []));

    expect(askedServices($source))->toBe([[[], 'US']]);
});

test('the number of services is capped', function () {
    config(['catalog.search.maximum_streaming_services' => 2]);
    $source = fakeServiceBrowse();

    $this->get(route('search', ['services' => '384,9,8']))->assertInertia(fn (Assert $page) => $page->where('selectedStreamingServices', [9, 384]));

    expect(askedServices($source))->toBe([[[9, 384], 'US']]);
});

test('switching tabs keeps the services the other tab offers too', function () {
    $source = fakeServiceBrowse();

    $this->get(route('search', ['tab' => 'tv', 'services' => '8,9,15']))->assertInertia(fn (Assert $page) => $page->where('selectedStreamingServices', [8, 15]));

    expect(askedServices($source))->toBe([[[8, 15], 'US']]);
});

test('services are ignored while the list is unavailable, and the page still browses', function () {
    $source = fakeServiceBrowse(withServices: false);

    $this->get(route('search', ['services' => '8']))->assertInertia(fn (Assert $page) => $page
        ->where('streamingServices', [])
        ->where('selectedStreamingServices', [])
        ->has('results.data', 3));

    expect(askedServices($source))->toBe([[[], 'US']]);
});

test('a search by Name takes no services', function () {
    $source = fakeServiceBrowse();
    $source->searches = ['inter' => [movieListings(7, 8)]];

    $this->get(route('search', ['q' => 'inter', 'services' => '8']))->assertInertia(fn (Assert $page) => $page
        ->where('selectedStreamingServices', [])
        ->has('results.data', 2));

    expect($source->browseCalls)->toBe([]);
});

test('choosing services makes Top rated the default sort', function () {
    $source = fakeServiceBrowse();

    $this->get(route('search', ['services' => '8']))->assertInertia(fn (Assert $page) => $page->where('sort', 'top_rated'));

    expect($source->browseFilters[0]->sort()->value)->toBe('top_rated');
});

test('each country\'s service list is cached by country and kind, and each set of services is its own Browse page', function () {
    $source = fakeServiceBrowse();

    $this->get(route('search', ['services' => '8']))->assertOk();
    $this->get(route('search', ['services' => '8']))->assertOk();
    $this->get(route('search', ['services' => '9']))->assertOk();
    $this->get(route('search', ['tab' => 'tv']))->assertOk();
    $this->withCookie('country', 'CA')->get(route('search', ['services' => '8']))->assertOk();

    expect(askedServices($source))->toBe([[[8], 'US'], [[9], 'US'], [[], 'US'], [[8], 'CA']])
        ->and($source->streamingServiceCalls)->toBe([['kind' => 'movie', 'region' => 'US'], ['kind' => 'tv', 'region' => 'US'], ['kind' => 'movie', 'region' => 'CA']]);
});

test('reloading userMovies alone reads the service lists from the cache only', function () {
    $source = fakeServiceBrowse();
    $this->freezeTime();
    $version = (string) $this->get(route('search', ['services' => '8']))->viewData('page')['version'];
    $reads = count($source->streamingServiceCalls);
    $partial = ['X-Inertia' => 'true', 'X-Inertia-Version' => $version, 'X-Inertia-Partial-Component' => 'Search', 'X-Inertia-Partial-Data' => 'userMovies'];

    $this->get(route('search', ['services' => '8']), $partial)->assertOk();

    expect(count($source->streamingServiceCalls))->toBe($reads);
});
