<?php

use App\Models\Movie;
use App\Models\Season;
use App\Models\TvShow;
use App\Services\Catalog\CacheKey;
use App\Services\Catalog\Sources\CatalogSource;
use App\Services\Catalog\Sources\Details\EpisodeDetails;
use App\Services\Catalog\Sources\Details\SeasonDetails;
use App\Services\Catalog\Sources\Details\TvShowDetails;
use App\Services\Catalog\Sources\FakeCatalogSource;
use App\Services\Catalog\Sources\GuardedCatalogSource;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Exceptions;
use Inertia\Testing\AssertableInertia as Assert;

function tvPageSource(): FakeCatalogSource
{
    $source = new FakeCatalogSource(tvShows: [new TvShowDetails(
        tmdbId: 87108, title: 'Chernobyl', overview: 'The cost of lies.', posterPath: '/poster.jpg',
        firstAirDate: '2019-05-06', lastAirDate: '2019-06-03', status: 'Ended', seasonCount: 1, episodeCount: 1,
        genres: [['id' => 18, 'name' => 'Drama']], voteAverage: 8.5, voteCount: 100,
        availability: [['serviceId' => 49, 'serviceName' => 'HBO', 'logoPath' => null, 'country' => 'US', 'offerType' => 'stream', 'displayPriority' => 1]],
        seasons: [new SeasonDetails(119454, 1, name: 'Miniseries', episodeCount: 1, episodes: [new EpisodeDetails(1725580, 1, 1, name: '1:23:45', airDate: '2019-05-06', runtime: 61)])],
    )]);
    app()->instance(CatalogSource::class, $source);

    return $source;
}

test('a guest opens a TV Show from its catalog id with a single complete fetch', function () {
    $source = tvPageSource();

    $response = $this->get('/tv-shows/tmdb/87108')->assertStatus(301)->assertHeader('X-Robots-Tag', 'noindex');

    $tvShow = TvShow::query()->sole();
    $response->assertRedirect(route('tv-shows.show', ['slug' => 'chernobyl', 'id' => $tvShow->id]));
    $this->get($response->headers->get('Location'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('tv-shows/Show')->where('tvShow.title', 'Chernobyl')->where('tvShow.overview', 'The cost of lies.')
        ->where('tvShow.posterPath', '/poster.jpg')->where('catalogStatus', 'fresh')
        ->where('canonicalUrl', route('tv-shows.show', ['slug' => 'chernobyl', 'id' => $tvShow->id])));
    expect($source->tvShowCalls)->toHaveCount(1);
    expect($tvShow->synced_at)->not->toBeNull();
    expect($tvShow->seasons->sole()->episodes->sole()->tmdb_id)->toBe(1725580);
    $this->assertDatabaseHas('tv_show_details', ['title_id' => $tvShow->id, 'first_air_date' => '2019-05-06', 'status' => 'Ended']);
    $this->assertDatabaseHas('availabilities', ['title_id' => $tvShow->id, 'streaming_service_id' => 49]);
    $this->assertDatabaseHas('ratings', ['source' => 'tmdb', 'source_id' => 'tv:87108']);
    expect($tvShow->genres->sole()->name)->toBe('Drama');
});

test('redirects a Movie URL for a TV Show to its canonical TV URL', function () {
    $tvShow = TvShow::factory()->create(['slug' => 'chernobyl']);

    $this->get(route('movies.show', ['slug' => 'old-name', 'id' => $tvShow->id]))
        ->assertStatus(301)->assertRedirect(route('tv-shows.show', ['slug' => 'chernobyl', 'id' => $tvShow->id]));
});

test('a refresh older than 24 hours updates the TV Show and its complete inventory', function () {
    $this->travelTo('2026-11-08 12:00:00');
    $tvShow = TvShow::factory()->create(['tmdb_id' => 87108, 'slug' => 'chernobyl', 'synced_at' => now()->subDays(2)]);
    $source = tvPageSource();

    $this->get(route('tv-shows.show', ['slug' => 'chernobyl', 'id' => $tvShow->id]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->where('catalogStatus', 'fresh')->where('tvShow.title', 'Chernobyl'));

    $this->assertDatabaseHas('titles', ['id' => $tvShow->id, 'synced_at' => '2026-11-08 12:00:00', 'last_viewed_at' => '2026-11-08 12:00:00']);
    expect($tvShow->seasons->sole()->episodes->sole()->tmdb_id)->toBe(1725580);
    expect($source->tvShowCalls)->toHaveCount(1);
});

test('a recent TV Show page uses stored details without fetching', function (int $hours) {
    $this->travelTo('2026-11-08 12:00:00');
    $source = tvPageSource();
    $source->tvShows = [new TvShowDetails(87108, title: 'Stored name')];
    $this->get('/tv-shows/tmdb/87108')->assertStatus(301);
    $tvShow = TvShow::query()->sole();
    $tvShow->update(['synced_at' => now()->subHours($hours)]);

    $this->get(route('tv-shows.show', ['slug' => 'stored-name', 'id' => $tvShow->id]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->where('tvShow.title', 'Stored name')->where('catalogStatus', 'fresh'));

    expect($source->tvShowCalls)->toHaveCount(1);
})->with([23, 24]);

test('one failed Season leaves the stored TV Show and Episodes unchanged and renders stale', function () {
    $this->travelTo('2026-11-08 12:00:00');
    $source = tvPageSource();
    $this->get('/tv-shows/tmdb/87108')->assertStatus(301);
    $tvShow = TvShow::query()->sole();
    $episode = $tvShow->episodes->sole();
    $tvShow->update(['synced_at' => now()->subDays(2), 'title' => 'Stored name']);
    $source->failingSeasons = [87108 => [1]];

    $this->get(route('tv-shows.show', ['slug' => 'chernobyl', 'id' => $tvShow->id]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->where('tvShow.title', 'Stored name')->where('catalogStatus', 'stale'));

    $this->assertDatabaseHas('titles', ['id' => $tvShow->id, 'synced_at' => '2026-11-06 12:00:00', 'title' => 'Stored name']);
    expect($episode->fresh()->getAttributes())->toBe($episode->getAttributes());
    $this->assertDatabaseCount('seasons', 1);
    $this->assertDatabaseCount('episodes', 1);
});

test('a failed first fetch returns 503 without creating any part of a TV Show', function (bool $seasonFailure) {
    $source = tvPageSource();
    $source->failing = ! $seasonFailure;
    $source->failingSeasons = $seasonFailure ? [87108 => [1]] : [];

    $this->get('/tv-shows/tmdb/87108')->assertServiceUnavailable()->assertHeader('Retry-After', '30');

    foreach (['titles', 'tv_show_details', 'seasons', 'episodes', 'availabilities', 'ratings'] as $table) {
        $this->assertDatabaseCount($table, 0);
    }
})->with([false, true]);

test('an unknown TV catalog id returns 404 without creating a row', function () {
    tvPageSource();

    $this->get('/tv-shows/tmdb/999')->assertNotFound();
    $this->assertDatabaseCount('titles', 0);
});

test('a TV Show without stored details gets 503 when its first page fetch fails', function () {
    $tvShow = TvShow::factory()->create(['synced_at' => null, 'slug' => null, 'title' => null]);
    $source = tvPageSource();
    $source->failing = true;

    $this->get(route('tv-shows.show', ['slug' => 'old-name', 'id' => $tvShow->id]))->assertServiceUnavailable();

    $this->assertDatabaseCount('seasons', 0);
    $this->assertDatabaseHas('titles', ['id' => $tvShow->id, 'synced_at' => null]);
});

test('redirects a wrong slug to the TV Show canonical URL', function () {
    $tvShow = TvShow::factory()->create(['slug' => 'chernobyl']);

    $this->get(route('tv-shows.show', ['slug' => 'old-name', 'id' => $tvShow->id]))
        ->assertStatus(301)->assertRedirect(route('tv-shows.show', ['slug' => 'chernobyl', 'id' => $tvShow->id]));
});

test('redirects a TV URL for a Movie to its canonical Movie URL', function () {
    $movie = Movie::factory()->create();

    $this->get(route('tv-shows.show', ['slug' => 'wrong-name', 'id' => $movie->id]))
        ->assertStatus(301)->assertRedirect($movie->canonicalUrl());
});

test('the disabled TV flag makes both routes and cross-kind redirects return 404 without fetching', function () {
    config(['catalog.tv_shows.enabled' => false]);
    $tvShow = TvShow::factory()->create();
    $movie = Movie::factory()->create();
    $source = tvPageSource();

    $this->get('/tv-shows/tmdb/87108')->assertNotFound();
    $this->get(route('tv-shows.show', ['slug' => $tvShow->slug, 'id' => $tvShow->id]))->assertNotFound();
    $this->get(route('movies.show', ['slug' => $tvShow->slug, 'id' => $tvShow->id]))->assertNotFound();
    $this->get(route('tv-shows.show', ['slug' => $movie->slug, 'id' => $movie->id]))->assertNotFound();

    expect($source->tvShowCalls)->toBeEmpty();
    $this->assertDatabaseCount('titles', 2);
    $this->assertDatabaseCount('seasons', 0);
});

test('TV and Movie entry routes share one creation budget', function () {
    config(['catalog.creation_limit.attempts' => 1]);
    $source = tvPageSource();

    $this->get('/tv-shows/tmdb/87108')->assertStatus(301);
    $this->get('/movies/tmdb/157336')->assertTooManyRequests()->assertHeader('Retry-After');
    $this->get('/tv-shows/tmdb/999')->assertTooManyRequests()->assertHeader('Retry-After');
    $this->get('/tv-shows/tmdb/87108')->assertStatus(301);

    expect($source->tvShowCalls)->toHaveCount(1);
    expect($source->movieCalls)->toBeEmpty();
});

test('invalid and oversized TV ids return 404 without fetching', function (string $path) {
    $source = tvPageSource();

    $this->get($path)->assertNotFound();

    expect($source->tvShowCalls)->toBeEmpty();
    $this->assertDatabaseCount('titles', 0);
})->with(['/tv-shows/tmdb/0', '/tv-shows/tmdb/nope', '/tv-shows/tmdb/999999999999999999999999', '/tv-shows/name-999999999999999999999999']);

test('a failed write rolls back the Title, metadata, Availability and rating together', function () {
    $other = TvShow::factory()->create();
    Season::factory()->for($other, 'title')->create(['tmdb_id' => 119454]);
    tvPageSource();
    Exceptions::fake();

    $this->get('/tv-shows/tmdb/87108')->assertInternalServerError();

    Exceptions::assertReported(UniqueConstraintViolationException::class);
    $this->assertDatabaseCount('titles', 1);
    $this->assertDatabaseCount('tv_show_details', 1);
    $this->assertDatabaseCount('seasons', 1);
    $this->assertDatabaseCount('episodes', 0);
    $this->assertDatabaseCount('availabilities', 0);
    $this->assertDatabaseCount('streaming_services', 0);
    $this->assertDatabaseCount('ratings', 0);
    $this->assertDatabaseCount('genre_title', 0);
});

test('a successful refresh matches Episodes by TMDB id when their number changes', function () {
    $source = tvPageSource();
    $this->get('/tv-shows/tmdb/87108')->assertStatus(301);
    $tvShow = TvShow::query()->sole();
    $episode = $tvShow->episodes->sole();
    $tvShow->update(['synced_at' => now()->subDays(2)]);
    $source->tvShows = [new TvShowDetails(87108, title: 'Chernobyl', seasons: [
        new SeasonDetails(119454, 2, episodes: [new EpisodeDetails(1725580, 2, 3, name: 'New name')]),
    ])];

    $this->get(route('tv-shows.show', ['slug' => 'chernobyl', 'id' => $tvShow->id]))->assertOk();

    $this->assertDatabaseCount('seasons', 1);
    $this->assertDatabaseCount('episodes', 1);
    $this->assertDatabaseHas('episodes', ['id' => $episode->id, 'tmdb_id' => 1725580, 'season_number' => 2, 'episode_number' => 3, 'name' => 'New name']);
});

test('a TV fetch is one failure against the shared circuit breaker', function () {
    config(['catalog.guard.failure_threshold' => 1]);
    $source = tvPageSource();
    $source->failingSeasons = [87108 => [1]];
    app()->instance(CatalogSource::class, new GuardedCatalogSource($source, new CacheKey));

    $this->get('/tv-shows/tmdb/87108')->assertServiceUnavailable();
    $source->failingSeasons = [];
    $this->get('/tv-shows/tmdb/87108')->assertServiceUnavailable();

    expect($source->tvShowCalls)->toHaveCount(1);
    $this->assertDatabaseCount('titles', 0);
});

test('a successful snapshot hides missing Episodes and restores their local identity on return', function () {
    $source = tvPageSource();
    $original = $source->tvShows;
    $this->get('/tv-shows/tmdb/87108')->assertStatus(301);
    $tvShow = TvShow::query()->sole();
    $episode = $tvShow->episodes->sole();
    $tvShow->update(['synced_at' => now()->subDays(2)]);
    $source->tvShows = [new TvShowDetails(87108, title: 'Chernobyl', seasons: [new SeasonDetails(119454, 1)])];

    $url = route('tv-shows.show', ['slug' => 'chernobyl', 'id' => $tvShow->id]);
    $this->get($url)->assertOk();
    expect($episode->fresh()->removed_at)->not->toBeNull();

    $tvShow->refresh()->update(['synced_at' => now()->subDays(2)]);
    $source->tvShows = $original;
    $this->get($url)->assertOk();
    $this->assertDatabaseCount('episodes', 1);
    $this->assertDatabaseHas('episodes', ['id' => $episode->id, 'removed_at' => null]);
});

test('a removed TV Show clears its source data while preserving row identities and is never fetched again', function () {
    $source = tvPageSource();
    $this->get('/tv-shows/tmdb/87108')->assertStatus(301);
    $tvShow = TvShow::query()->sole();
    $seasonId = $tvShow->seasons->sole()->id;
    $episodeId = $tvShow->episodes->sole()->id;
    $tvShow->update(['synced_at' => now()->subDays(2)]);
    $source->tvShows = [];
    $url = route('tv-shows.show', ['slug' => 'chernobyl', 'id' => $tvShow->id]);

    $this->get($url)->assertGone();

    $this->assertDatabaseHas('titles', ['id' => $tvShow->id, 'tmdb_id' => 87108, 'title' => null, 'overview' => null, 'poster_path' => null, 'slug' => null, 'synced_at' => null]);
    expect($tvShow->fresh()->removed_at)->not->toBeNull();
    $this->assertDatabaseHas('tv_show_details', ['title_id' => $tvShow->id, 'first_air_date' => null, 'status' => null]);
    $this->assertDatabaseHas('seasons', ['id' => $seasonId, 'tmdb_id' => 119454, 'name' => null]);
    $this->assertDatabaseHas('episodes', ['id' => $episodeId, 'tmdb_id' => 1725580, 'name' => null]);
    $this->assertDatabaseCount('availabilities', 0);
    $this->assertDatabaseCount('genre_title', 0);
    $this->assertDatabaseCount('ratings', 0);

    $this->get($url)->assertGone();
    expect($source->tvShowCalls)->toHaveCount(2);
});
