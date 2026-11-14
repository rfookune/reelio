<?php

use App\Jobs\RecordAnalyticsEvent;
use App\Models\Movie;
use App\Models\TvShow;
use App\Services\Catalog\Sources\CatalogSource;
use App\Services\Catalog\Sources\Details\SeasonDetails;
use App\Services\Catalog\Sources\Details\TvShowDetails;
use App\Services\Catalog\Sources\FakeCatalogSource;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

/** A series-wide cast of the given size, each actor with their Episode count. */
function tvCast(int $size): array
{
    return array_map(fn (int $i): array => ['id' => $i, 'name' => "Actor {$i}", 'character' => "Role {$i}", 'profilePath' => null, 'episodeCount' => $i], range(1, $size));
}

/** Binds a source that knows Chernobyl with the given series-wide cast and crew, and returns its stored TV Show. */
function tvCastShow(array $cast = [], array $crew = []): TvShow
{
    $source = new FakeCatalogSource(tvShows: [new TvShowDetails(87108, title: 'Chernobyl', firstAirDate: '2019-05-06', cast: $cast, crew: $crew, seasons: [new SeasonDetails(1, 1)])]);
    app()->instance(CatalogSource::class, $source);
    test()->get('/tv-shows/tmdb/87108')->assertStatus(301);

    return TvShow::query()->sole();
}

test('the full cast page lists the whole series-wide cast and crew without recording a view', function () {
    $crew = [['id' => 9, 'name' => 'Craig Mazin', 'job' => 'Writer'], ['id' => 9, 'name' => 'Craig Mazin', 'job' => 'Executive Producer']];
    $tvShow = tvCastShow(tvCast(30), $crew);
    $tvShow->update(['last_viewed_at' => '2026-11-01 00:00:00']);
    Queue::fake([RecordAnalyticsEvent::class]);

    $this->get(route('tv-shows.cast', ['slug' => $tvShow->slug, 'id' => $tvShow->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('titles/Cast')->where('kind', 'tv')
            ->where('canonicalUrl', route('tv-shows.cast', ['slug' => $tvShow->slug, 'id' => $tvShow->id]))
            ->where('titleUrl', route('tv-shows.show', ['slug' => $tvShow->slug, 'id' => $tvShow->id]))
            ->where('title.name', 'Chernobyl')
            ->where('title.year', 2019)
            ->where('title.cast', tvCast(30))
            ->where('title.crew', $crew)
            ->where('title.detailsUnavailable', false));

    $this->assertDatabaseHas('titles', ['id' => $tvShow->id, 'last_viewed_at' => '2026-11-01 00:00:00']);
    Queue::assertNothingPushed();
});

test('the full cast page is addressed as /tv-shows/{slug}-{id}/casts', function () {
    $tvShow = tvCastShow();

    expect(route('tv-shows.cast', ['slug' => 'chernobyl', 'id' => $tvShow->id], false))->toBe("/tv-shows/chernobyl-{$tvShow->id}/casts");
});

test('a stale slug permanently redirects to the full cast page', function () {
    $tvShow = tvCastShow();

    $this->get("/tv-shows/an-old-name-{$tvShow->id}/casts")
        ->assertStatus(301)
        ->assertRedirect(route('tv-shows.cast', ['slug' => $tvShow->slug, 'id' => $tvShow->id]));
});

test('the full cast page of a Removed TV Show is the title-less 410', function () {
    $tvShow = TvShow::factory()->removed()->create();

    $this->get("/tv-shows/tv-show-{$tvShow->id}/casts")->assertGone()->assertInertia(fn (Assert $page) => $page->component('Error')->where('title', 'This TV show is no longer available.')->missing('titleUrl')->missing('kind'));
});

test('a TV Show without details is refreshed before its slug is checked', function () {
    $tvShow = TvShow::factory()->create(['tmdb_id' => 87108, 'synced_at' => null, 'slug' => null, 'title' => null]);
    app()->instance(CatalogSource::class, new FakeCatalogSource(tvShows: [new TvShowDetails(87108, title: 'Chernobyl', firstAirDate: '2019-05-06', cast: tvCast(2), seasons: [new SeasonDetails(1, 1)])]));

    $this->get("/tv-shows/old-name-{$tvShow->id}/casts")
        ->assertStatus(301)
        ->assertRedirect(route('tv-shows.cast', ['slug' => 'chernobyl', 'id' => $tvShow->id]));
});

test('a TV Show without details is a 503 while its refresh fails', function () {
    $tvShow = TvShow::factory()->create(['synced_at' => null, 'slug' => null, 'title' => null]);
    app()->instance(CatalogSource::class, new FakeCatalogSource(failing: true));

    $this->get("/tv-shows/old-name-{$tvShow->id}/casts")->assertServiceUnavailable()->assertHeader('Retry-After', '30');
});

test('with stored details but no cached cast and the source down, the panel is flagged', function () {
    $tvShow = tvCastShow(tvCast(3));
    $tvShow->update(['synced_at' => now()->subDays(2)]);
    Cache::flush();
    app()->instance(CatalogSource::class, new FakeCatalogSource(failing: true));

    $this->get(route('tv-shows.cast', ['slug' => $tvShow->slug, 'id' => $tvShow->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('title.detailsUnavailable', true)->where('title.cast', [])->where('title.crew', []));
});

test('the full cast page is not found for a missing TV Show, a Movie, or available for an existing TV Show', function () {
    $movie = Movie::factory()->create();
    $tvShow = TvShow::factory()->create();

    $this->get('/tv-shows/missing-999999/casts')->assertNotFound();
    $this->get("/tv-shows/{$movie->slug}-{$movie->id}/casts")->assertNotFound();

    app()->instance(CatalogSource::class, new FakeCatalogSource(failing: true));
    $this->get(route('tv-shows.cast', ['slug' => $tvShow->slug, 'id' => $tvShow->id]))->assertOk();
});

test('the TV Show page shows the top twelve of the series-wide cast with the billed count and the full cast URL', function (int $billed) {
    $tvShow = tvCastShow(tvCast($billed));

    $this->get($tvShow->canonicalUrl())->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('tvShow.cast', min($billed, 12))
        ->where('tvShow.castCount', $billed)
        ->where('castUrl', route('tv-shows.cast', ['slug' => $tvShow->slug, 'id' => $tvShow->id]))
        ->where('tvShow.cast.0.episodeCount', 1));
})->with([12, 13]);
