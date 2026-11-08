<?php

use App\Models\TvShow;
use App\Services\Catalog\Sources\CatalogSource;
use App\Services\Catalog\Sources\Details\TvShowDetails;
use App\Services\Catalog\Sources\FakeCatalogSource;

it('renders TV metadata in the document head under the real CSP', function () {
    $tvShow = TvShow::factory()->create(['tmdb_id' => 87108, 'slug' => 'chernobyl', 'synced_at' => now()->subDays(2)]);
    app()->instance(CatalogSource::class, new FakeCatalogSource(tvShows: [new TvShowDetails(
        87108, title: 'Chernobyl', originalTitle: 'Чернобыль', overview: 'The cost of lies.', tagline: 'What is the cost of lies?',
        firstAirDate: '2019-05-06', lastAirDate: '2019-06-03', status: 'Ended', backdropPath: '/backdrop.jpg',
        creators: ['Craig Mazin'], networks: ['HBO'], certification: 'TV-MA',
        cast: [['id' => 1, 'name' => 'Jared Harris', 'character' => 'Valery Legasov', 'profilePath' => null]],
    )]));

    $page = visitWithCsp($tvShow->canonicalUrl());
    $page->assertSee('Chernobyl')->assertSee('Чернобыль')->assertSee('Craig Mazin')->assertSee('HBO')->assertSee('Jared Harris')
        ->assertNotPresent('[data-slot="full-cast-link"]')->assertNotPresent('[data-slot="trailer"]')
        ->assertAttribute('meta[property="og:type"]', 'content', 'video.tv_show')
        ->assertAttribute('link[rel="canonical"]', 'href', $tvShow->canonicalUrl())
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'tv-show-content-desktop');
    expect($page->script('document.title'))->toBe('Chernobyl (2019): where to watch, cast and ratings · Reelio');
    expect($page->script('JSON.parse(document.head.querySelector(\'script[type="application/ld+json"]\').textContent)'))->toMatchArray([
        '@type' => 'TVSeries', 'name' => 'Chernobyl', 'startDate' => '2019-05-06',
        'creator' => [['@type' => 'Person', 'name' => 'Craig Mazin']], 'numberOfSeasons' => 0, 'numberOfEpisodes' => 0,
    ]);
    visitWithCsp($tvShow->canonicalUrl(), 390, 844)->assertSee('Chernobyl')->assertNoJavaScriptErrors()->screenshot(filename: 'tv-show-content-mobile');
});
