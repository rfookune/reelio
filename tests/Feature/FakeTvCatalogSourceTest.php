<?php

use App\Services\Catalog\CacheKey;
use App\Services\Catalog\CatalogContext;
use App\Services\Catalog\Sources\EpisodeDetails;
use App\Services\Catalog\Sources\FakeCatalogSource;
use App\Services\Catalog\Sources\GuardedCatalogSource;
use App\Services\Catalog\Sources\SeasonDetails;
use App\Services\Catalog\Sources\SourceUnavailable;
use App\Services\Catalog\Sources\TvShowDetails;

test('returns a TV Show with Seasons and Episodes and reports unknown TV Shows', function () {
    $tvShow = new TvShowDetails(87108, seasons: [new SeasonDetails(119454, 1, episodes: [new EpisodeDetails(1725580, 1, 1)])]);
    $source = new FakeCatalogSource(tvShows: [$tvShow]);
    $context = new CatalogContext('en-US');

    expect($source->tvShow(87108, $context))->toBe($tvShow);
    expect($source->tvShow(999, $context))->toBeNull();
    expect($source->tvShowCalls)->toBe([['tmdbId' => 87108, 'context' => $context], ['tmdbId' => 999, 'context' => $context]]);
    expect($source->seasonCalls)->toBe([['tmdbId' => 87108, 'seasonNumber' => 1]]);
});

test('can fail one TV Show or one Season and recover on a later call', function (string $failure) {
    $tvShow = new TvShowDetails(87108, seasons: [new SeasonDetails(119454, 1)]);
    $other = new TvShowDetails(153312);
    $source = new FakeCatalogSource(tvShows: [$tvShow, $other], failingTvShows: $failure === 'details' ? [87108] : [], failingSeasons: $failure === 'season' ? [87108 => [1]] : []);
    $context = new CatalogContext('en-US');

    expect(fn () => $source->tvShow(87108, $context))->toThrow(SourceUnavailable::class);
    expect($source->tvShow(153312, $context))->toBe($other);

    $source->failingTvShows = [];
    $source->failingSeasons = [];
    expect($source->tvShow(87108, $context))->toBe($tvShow);
})->with(['details', 'season']);

test('can fail every TV Show call', function () {
    $source = new FakeCatalogSource(failing: true);
    $context = new CatalogContext('en-US');

    expect(fn () => $source->tvShow(87108, $context))->toThrow(SourceUnavailable::class);
    expect(fn () => $source->tvShow(153312, $context))->toThrow(SourceUnavailable::class);
});

test('guards the whole TV Show fetch as a single interactive call', function () {
    config(['catalog.guard.live_requests_per_second' => 1]);
    $tvShow = new TvShowDetails(87108, seasons: [new SeasonDetails(119454, 1), new SeasonDetails(119455, 2)]);
    $source = new FakeCatalogSource(tvShows: [$tvShow]);
    $guarded = new GuardedCatalogSource($source, new CacheKey);

    expect($guarded->tvShow(87108, new CatalogContext('en-US')))->toBe($tvShow);
    expect(fn () => $guarded->tvShow(87108, new CatalogContext('en-US')))->toThrow(SourceUnavailable::class);
    expect($source->tvShowCalls)->toHaveCount(1);
});
