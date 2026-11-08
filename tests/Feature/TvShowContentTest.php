<?php

use App\Models\TvShow;
use App\Services\Catalog\CacheKey;
use App\Services\Catalog\CatalogContext;
use App\Services\Catalog\Sources\CatalogSource;
use App\Services\Catalog\Sources\Details\EpisodeDetails;
use App\Services\Catalog\Sources\Details\SeasonDetails;
use App\Services\Catalog\Sources\Details\TvShowDetails;
use App\Services\Catalog\Sources\FakeCatalogSource;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;

function tvContentSource(): FakeCatalogSource
{
    $source = new FakeCatalogSource(tvShows: [new TvShowDetails(
        tmdbId: 87108, title: 'Chernobyl', originalTitle: 'Чернобыль', originalLanguage: 'ru', overview: 'The cost of lies.', tagline: 'What is the cost of lies?',
        posterPath: '/poster.jpg', backdropPath: '/backdrop.jpg', firstAirDate: '2019-05-06', lastAirDate: '2019-06-03', status: 'Ended',
        creators: ['Craig Mazin'], networks: ['HBO'], certification: 'TV-MA',
        trailer: ['key' => 's9APLXM9Ei8', 'name' => 'Official trailer', 'type' => 'Trailer'],
        cast: array_map(fn (int $id): array => ['id' => $id, 'name' => "Actor {$id}", 'character' => "Character {$id}", 'profilePath' => null], range(1, 15)),
        seasons: [
            new SeasonDetails(119454, 1, episodes: [new EpisodeDetails(1725580, 1, 1, airDate: '2019-05-06'), new EpisodeDetails(1725581, 1, 2, airDate: '2099-01-01'), new EpisodeDetails(1725582, 1, 3)]),
            new SeasonDetails(119455, 0, episodes: [new EpisodeDetails(1725590, 0, 1, airDate: '2019-05-01')]),
            new SeasonDetails(119456, 2, episodes: [new EpisodeDetails(1725591, 2, 1, airDate: '2099-01-01')]),
        ],
    )]);
    app()->instance(CatalogSource::class, $source);

    return $source;
}

test('renders stored facts and appended details together with aired counts and top twelve cast', function () {
    $this->travelTo('2026-11-08');
    $source = tvContentSource();
    $entry = $this->get('/tv-shows/tmdb/87108')->assertStatus(301);

    $this->get($entry->headers->get('Location'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('tvShow.originalTitle', 'Чернобыль')->where('tvShow.tagline', 'What is the cost of lies?')
        ->where('tvShow.firstAirDate', '2019-05-06')->where('tvShow.lastAirDate', '2019-06-03')
        ->where('tvShow.status', 'Ended')->where('tvShow.creators', ['Craig Mazin'])->where('tvShow.networks', ['HBO'])
        ->where('tvShow.certification', 'TV-MA')->where('tvShow.seasonCount', 1)->where('tvShow.episodeCount', 1)
        ->where('tvShow.trailer.key', 's9APLXM9Ei8')->has('tvShow.cast', 12)->where('tvShow.cast.11.name', 'Actor 12')
        ->where('tvShow.detailsUnavailable', false));
    expect($source->tvShowCalls)->toHaveCount(1);
});

test('a failed refresh keeps cached appended details as stale', function () {
    $source = tvContentSource();
    $entry = $this->get('/tv-shows/tmdb/87108')->assertStatus(301);
    $tvShow = TvShow::query()->sole();
    $tvShow->update(['synced_at' => now()->subDays(2)]);
    $source->failing = true;

    $this->get($entry->headers->get('Location'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('catalogStatus', 'stale')->where('tvShow.detailsUnavailable', false)
        ->where('tvShow.creators', ['Craig Mazin'])->where('tvShow.certification', 'TV-MA')->has('tvShow.cast', 12));
});

test('an outage without cached appends keeps stored facts and exposes unavailable panels', function () {
    $source = tvContentSource();
    $entry = $this->get('/tv-shows/tmdb/87108')->assertStatus(301);
    $tvShow = TvShow::query()->sole();
    $tvShow->update(['synced_at' => now()->subDays(2)]);
    Cache::flush();
    $source->failing = true;

    $this->get($entry->headers->get('Location'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('catalogStatus', 'stale')->where('tvShow.title', 'Chernobyl')->where('tvShow.status', 'Ended')
        ->where('tvShow.detailsUnavailable', true)->where('tvShow.creators', [])->where('tvShow.networks', [])
        ->where('tvShow.certification', null)->where('tvShow.cast', [])->where('tvShow.trailer', null));
});

test('counts ignore removed Episodes and removed Seasons even when their Episodes have aired', function () {
    $source = tvContentSource();
    $entry = $this->get('/tv-shows/tmdb/87108')->assertStatus(301);
    $tvShow = TvShow::query()->sole();
    $tvShow->episodes()->where('tmdb_id', 1725580)->update(['removed_at' => now()]);
    $tvShow->seasons()->where('season_number', 2)->update(['removed_at' => now()]);
    $tvShow->episodes()->where('tmdb_id', 1725591)->update(['air_date' => '2020-01-01']);

    $this->get($entry->headers->get('Location'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('tvShow.seasonCount', 0)->where('tvShow.episodeCount', 0));
});

test('rebuilds missing appended data on a recently synced TV Show without waiting a day', function () {
    $source = tvContentSource();
    $entry = $this->get('/tv-shows/tmdb/87108')->assertStatus(301);
    Cache::flush();

    $this->get($entry->headers->get('Location'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('tvShow.detailsUnavailable', false)->where('tvShow.creators', ['Craig Mazin']));
    expect($source->tvShowCalls)->toHaveCount(2);
});

test('removal deletes the cached appends rather than keeping source data behind the removed marker', function () {
    $source = tvContentSource();
    $entry = $this->get('/tv-shows/tmdb/87108')->assertStatus(301);
    $tvShow = TvShow::query()->sole();
    $cacheKey = app(CacheKey::class)->build('tv-page-details', ['tmdbId' => 87108], new CatalogContext('en-US'));
    expect(Cache::has($cacheKey))->toBeTrue();
    $tvShow->update(['synced_at' => now()->subDays(2)]);
    $source->tvShows = [];

    $this->get($entry->headers->get('Location'))->assertGone();

    expect(Cache::has($cacheKey))->toBeFalse();
});
