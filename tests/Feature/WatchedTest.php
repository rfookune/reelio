<?php

use App\Jobs\RecordAnalyticsEvent;
use App\Models\Movie;
use App\Models\User;
use App\Models\UserMovie;
use App\Services\Catalog\Sources\CatalogSource;
use App\Services\Catalog\Sources\Details\MovieDetails;
use App\Services\Catalog\Sources\FakeCatalogSource;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Every row of the spec's user–movie lifecycle table for Watched, at 2026-11-06 12:00:00: the facts before (a
 * list of zero or one rows), the action, the facts after, and the events recorded as `[name, was_on_watchlist]`.
 */
dataset('lifecycle', [
    'mark as watched, neither' => [
        [], 'put',
        [['watchlisted_at' => null, 'watched_at' => '2026-11-06 12:00:00']],
        [['movie_marked_watched', false]],
    ],
    'mark as watched, on the Watchlist' => [
        [['watchlisted_at' => '2026-10-02 09:00:00', 'watched_at' => null]], 'put',
        [['watchlisted_at' => null, 'watched_at' => '2026-11-06 12:00:00']],
        [['movie_marked_watched', true]],
    ],
    'mark as watched, Watched and on the Watchlist (a rewatch)' => [
        [['watchlisted_at' => '2026-10-02 09:00:00', 'watched_at' => '2026-10-01 09:00:00']], 'put',
        [['watchlisted_at' => null, 'watched_at' => '2026-11-06 12:00:00']],
        [['movie_marked_watched', true]],
    ],
    'mark as watched, Watched and not on the Watchlist' => [
        [['watchlisted_at' => null, 'watched_at' => '2026-10-01 09:00:00']], 'put',
        [['watchlisted_at' => null, 'watched_at' => '2026-10-01 09:00:00']],
        [],
    ],
    'clear Watched, Watched and on the Watchlist' => [
        [['watchlisted_at' => '2026-10-02 09:00:00', 'watched_at' => '2026-10-01 09:00:00']], 'delete',
        [['watchlisted_at' => '2026-10-02 09:00:00', 'watched_at' => null]],
        [['movie_unmarked_watched', true]],
    ],
    'clear Watched, only Watched' => [
        [['watchlisted_at' => null, 'watched_at' => '2026-10-01 09:00:00']], 'delete',
        [],
        [['movie_unmarked_watched', false]],
    ],
    'clear Watched, only on the Watchlist' => [
        [['watchlisted_at' => '2026-10-02 09:00:00', 'watched_at' => null]], 'delete',
        [['watchlisted_at' => '2026-10-02 09:00:00', 'watched_at' => null]],
        [],
    ],
    'clear Watched, neither' => [[], 'delete', [], []],
]);

test('Watched actions follow the lifecycle', function (array $before, string $method, array $after, array $events) {
    $this->travelTo('2026-11-06 12:00:00');
    Queue::fake([RecordAnalyticsEvent::class]);
    $user = User::factory()->create();
    $movie = Movie::factory()->create();
    UserMovie::factory()->for($user)->for($movie)->forEachSequence(...$before)->create();

    $this->actingAs($user)
        ->from($movie->canonicalUrl())
        ->{$method}("/movies/tmdb/{$movie->tmdb_id}/watched", ['source' => 'movie'])
        ->assertRedirect($movie->canonicalUrl());

    expect(UserMovie::query()->whereBelongsTo($user)->whereBelongsTo($movie)->get()->map(fn (UserMovie $row): array => [
        'watchlisted_at' => $row->watchlisted_at?->toDateTimeString(),
        'watched_at' => $row->watched_at?->toDateTimeString(),
    ])->all())->toBe($after)
        ->and(Queue::pushed(RecordAnalyticsEvent::class)->map(fn (RecordAnalyticsEvent $job): array => [$job->userId, $job->name, $job->properties])->all())
        ->toBe(array_map(fn (array $event): array => [$user->id, $event[0], ['movie_id' => $movie->id, 'source' => 'movie', 'was_on_watchlist' => $event[1]]], $events));
})->with('lifecycle');

test('marking a catalog id with no Movie row as watched creates the Movie through the Catalog', function () {
    $this->travelTo('2026-11-06 12:00:00');
    $user = User::factory()->create();
    fakeCatalogKnowing(new MovieDetails(tmdbId: 157336, title: 'Interstellar'));

    $this->actingAs($user)->put('/movies/tmdb/157336/watched', ['source' => 'search'])->assertRedirect();

    $movie = Movie::query()->sole();
    expect($movie->tmdb_id)->toBe(157336);
    $this->assertDatabaseHas('user_titles', ['user_id' => $user->id, 'title_id' => $movie->id, 'watchlisted_at' => null, 'watched_at' => '2026-11-06 12:00:00']);
});

test('marking a new catalog id as watched shares the entry route\'s creation limit', function () {
    $this->freezeTime();
    $this->actingAs(User::factory()->create());

    for ($attempt = 1; $attempt <= 20; $attempt++) {
        $this->get('/movies/tmdb/157336')->assertNotFound();
    }

    $this->put('/movies/tmdb/157336/watched', ['source' => 'search'])->assertTooManyRequests()->assertHeader('Retry-After', '60');
});

test('clearing Watched on a catalog id with no Movie row neither asks the source nor creates a row', function () {
    $this->app->instance(CatalogSource::class, new FakeCatalogSource(failing: true));

    $this->actingAs(User::factory()->create())->delete('/movies/tmdb/157336/watched', ['source' => 'watched'])->assertRedirect();

    $this->assertDatabaseCount('titles', 0);
});

test('a Removed Movie cannot be marked as watched', function () {
    $movie = Movie::factory()->removed()->create();

    $this->actingAs(User::factory()->create())->put("/movies/tmdb/{$movie->tmdb_id}/watched", ['source' => 'watched'])->assertGone();

    $this->assertDatabaseCount('user_titles', 0);
});

test('a Removed Movie\'s Watched record can still be cleared', function () {
    $facts = UserMovie::factory()->watched()->for(Movie::factory()->removed())->create();

    $this->actingAs($facts->user)->delete("/movies/tmdb/{$facts->movie->tmdb_id}/watched", ['source' => 'watched'])->assertRedirect();

    $this->assertDatabaseCount('user_titles', 0);
});

test('guests are sent to sign in and nothing is marked', function (string $method) {
    $movie = Movie::factory()->create();

    $this->{$method}("/movies/tmdb/{$movie->tmdb_id}/watched", ['source' => 'movie'])->assertRedirect(route('login'));

    $this->assertDatabaseCount('user_titles', 0);
})->with(['put', 'delete']);

test('a user who has not verified their email can still mark as watched', function () {
    $user = User::factory()->unverified()->create();
    $movie = Movie::factory()->create();

    $this->actingAs($user)->put("/movies/tmdb/{$movie->tmdb_id}/watched", ['source' => 'movie'])->assertRedirect();

    $this->assertDatabaseHas('user_titles', ['user_id' => $user->id, 'title_id' => $movie->id]);
});

test('Watchlist and Watched actions share one limit of sixty a minute', function () {
    $this->freezeTime();
    $movie = Movie::factory()->create();
    $this->actingAs(User::factory()->create());

    for ($attempt = 1; $attempt <= 30; $attempt++) {
        $this->put("/movies/tmdb/{$movie->tmdb_id}/watchlist", ['source' => 'movie'])->assertRedirect();
        $this->put("/movies/tmdb/{$movie->tmdb_id}/watched", ['source' => 'movie'])->assertRedirect();
    }

    $this->delete("/movies/tmdb/{$movie->tmdb_id}/watched", ['source' => 'movie'])->assertTooManyRequests()->assertHeader('Retry-After', '60');
});

test('the Movie page carries the Watched date with the viewer\'s facts', function () {
    $facts = UserMovie::factory()->create(['watchlisted_at' => '2026-10-02 09:00:00', 'watched_at' => '2026-10-01 09:00:00']);
    fakeCatalogKnowing($facts->movie);

    $this->actingAs($facts->user)->get($facts->movie->canonicalUrl())->assertInertia(fn (Assert $page) => $page
        ->where("userMovies.{$facts->movie->tmdb_id}", ['watchlisted' => true, 'watched' => true, 'watchedDate' => '2026-10-01T09:00:00.000000Z']));
});

test('an action on Watched needs a known source', function () {
    $movie = Movie::factory()->create();

    $this->actingAs(User::factory()->create())->put("/movies/tmdb/{$movie->tmdb_id}/watched", ['source' => 'homepage'])->assertSessionHasErrors('source');

    $this->assertDatabaseCount('user_titles', 0);
});

test('clearing Watched never touches another user\'s record', function () {
    $theirs = UserMovie::factory()->watched()->create();

    $this->actingAs(User::factory()->create())
        ->delete("/movies/tmdb/{$theirs->movie->tmdb_id}/watched", ['source' => 'watched', 'user_id' => $theirs->user_id])
        ->assertRedirect();

    $this->assertModelExists($theirs);
});
