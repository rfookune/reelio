<?php

use App\Jobs\RecordAnalyticsEvent;
use App\Models\Movie;
use App\Models\TvShow;
use App\Services\Catalog\Sources\CatalogSource;
use App\Services\Catalog\Sources\FakeCatalogSource;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

test('the full cast page lists the whole billed cast without recording a view', function () {
    $movie = Movie::factory()->create(['title' => 'Avengers: Doomsday', 'slug' => 'avengers-doomsday', 'release_date' => '2026-12-18', 'last_viewed_at' => '2026-11-01 00:00:00']);
    $cast = array_map(fn (int $i) => ['id' => $i, 'name' => "Actor {$i}", 'character' => "Role {$i}", 'profilePath' => null], range(1, 30));
    fakeCatalogKnowing(sourceDetailsFor($movie, cast: $cast));
    Queue::fake([RecordAnalyticsEvent::class]);

    $this->get("/movies/avengers-doomsday-{$movie->id}/casts")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('movies/Cast')
            ->where('canonicalUrl', route('movies.cast', ['slug' => 'avengers-doomsday', 'id' => $movie->id]))
            ->where('movieUrl', route('movies.show', ['slug' => 'avengers-doomsday', 'id' => $movie->id]))
            ->where('movie.title', 'Avengers: Doomsday')
            ->where('movie.year', 2026)
            ->where('movie.cast', $cast)
            ->where('movie.crew', [])
            ->where('movie.detailsUnavailable', false));

    $this->assertDatabaseHas('titles', ['id' => $movie->id, 'last_viewed_at' => '2026-11-01 00:00:00']);
    Queue::assertNothingPushed();
});

test('a stale slug permanently redirects to the full cast page', function () {
    $movie = Movie::factory()->create(['slug' => 'the-correct-title']);

    $this->get("/movies/an-old-title-{$movie->id}/casts")
        ->assertStatus(301)
        ->assertRedirect(route('movies.cast', ['slug' => 'the-correct-title', 'id' => $movie->id]));
});

test('the full cast page of a Removed Movie is gone', function () {
    $movie = Movie::factory()->removed()->create();

    $this->get("/movies/movie-{$movie->id}/casts")->assertGone();
});

test('the full cast page of a missing Movie is not found', function () {
    $this->get('/movies/missing-999999/casts')->assertNotFound();
});

test('with no cached details and the source down, the full cast is flagged unavailable', function () {
    $movie = Movie::factory()->create();
    $this->app->instance(CatalogSource::class, new FakeCatalogSource(failing: true));

    $this->get(route('movies.cast', ['slug' => $movie->slug, 'id' => $movie->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('movie.detailsUnavailable', true)
            ->where('movie.cast', [])
            ->where('movie.crew', []));
});

test('the full cast page carries the directors, writers and producers', function () {
    $movie = Movie::factory()->create();
    $crew = [
        ['id' => 525, 'name' => 'Christopher Nolan', 'job' => 'Director'],
        ['id' => 525, 'name' => 'Christopher Nolan', 'job' => 'Producer'],
    ];
    fakeCatalogKnowing(sourceDetailsFor($movie, crew: $crew));

    $this->get(route('movies.cast', ['slug' => $movie->slug, 'id' => $movie->id]))
        ->assertInertia(fn (Assert $page) => $page->where('movie.crew', $crew));
});

test('a TV Show id cannot open the Movie full-cast page', function () {
    $tvShow = TvShow::factory()->create();

    $this->get(route('movies.cast', ['slug' => $tvShow->slug, 'id' => $tvShow->id]))->assertNotFound();
});
