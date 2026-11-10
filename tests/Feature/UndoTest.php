<?php

use App\Jobs\RecordAnalyticsEvent;
use App\Models\Movie;
use App\Models\User;
use App\Models\UserMovie;
use App\Services\Catalog\Sources\CatalogSource;
use App\Services\Catalog\Sources\FakeCatalogSource;
use Illuminate\Support\Facades\Queue;

/**
 * The user's facts about a Movie as `[watchlisted_at, watched_at]` rows, at whole seconds.
 *
 * @return list<array{watchlisted_at: ?string, watched_at: ?string}>
 */
function factsAbout(User $user, Movie $movie): array
{
    return UserMovie::query()->whereBelongsTo($user)->whereBelongsTo($movie)->get()->map(fn (UserMovie $row): array => [
        'watchlisted_at' => $row->watchlisted_at?->toDateTimeString(),
        'watched_at' => $row->watched_at?->toDateTimeString(),
    ])->all();
}

test('undoing an add takes the Movie back off the Watchlist and records the undo', function () {
    $this->travelTo('2026-11-06 12:00:00');
    Queue::fake([RecordAnalyticsEvent::class]);
    $user = User::factory()->create();
    $movie = Movie::factory()->create();
    $this->actingAs($user)->from($movie->canonicalUrl());

    $this->put("/movies/tmdb/{$movie->tmdb_id}/watchlist", ['source' => 'movie']);
    $this->post("/movies/tmdb/{$movie->tmdb_id}/undo", ['action' => 'add_to_watchlist'])
        ->assertSessionHasNoErrors()
        ->assertRedirect($movie->canonicalUrl());

    expect(factsAbout($user, $movie))->toBe([])
        ->and(Queue::pushed(RecordAnalyticsEvent::class)->map(fn (RecordAnalyticsEvent $job): array => [$job->name, $job->properties])->all())
        ->toBe([
            ['movie_watchlisted', ['movie_id' => $movie->id, 'source' => 'movie']],
            ['user_movie_action_undone', ['movie_id' => $movie->id, 'action' => 'add_to_watchlist']],
        ]);
});

/**
 * Each action from a state where it changes something, at 2026-11-06 12:00:00: the facts before (zero or one rows),
 * the action's method and path, and the action Undo names.
 */
dataset('actions', [
    'add, Watched' => [[['watchlisted_at' => null, 'watched_at' => '2026-10-01 09:00:00']], 'put', 'watchlist', 'add_to_watchlist'],
    'remove, on the Watchlist and Watched' => [[['watchlisted_at' => '2026-10-02 09:00:00', 'watched_at' => '2026-10-01 09:00:00']], 'delete', 'watchlist', 'remove_from_watchlist'],
    'remove, only on the Watchlist' => [[['watchlisted_at' => '2026-10-02 09:00:00', 'watched_at' => null]], 'delete', 'watchlist', 'remove_from_watchlist'],
    'mark as watched, neither' => [[], 'put', 'watched', 'mark_as_watched'],
    'mark as watched, on the Watchlist' => [[['watchlisted_at' => '2026-10-02 09:00:00', 'watched_at' => null]], 'put', 'watched', 'mark_as_watched'],
    'mark as watched, a rewatch' => [[['watchlisted_at' => '2026-10-02 09:00:00', 'watched_at' => '2026-10-01 09:00:00']], 'put', 'watched', 'mark_as_watched'],
    'clear Watched, only Watched' => [[['watchlisted_at' => null, 'watched_at' => '2026-10-01 09:00:00']], 'delete', 'watched', 'clear_watched'],
    'clear Watched, on the Watchlist and Watched' => [[['watchlisted_at' => '2026-10-02 09:00:00', 'watched_at' => '2026-10-01 09:00:00']], 'delete', 'watched', 'clear_watched'],
]);

test('Undo restores exactly the earlier facts, dates included', function (array $before, string $method, string $fact, string $action) {
    $this->travelTo('2026-11-06 12:00:00');
    $user = User::factory()->create();
    $movie = Movie::factory()->create();
    UserMovie::factory()->for($user)->for($movie)->forEachSequence(...$before)->create();
    $this->actingAs($user);

    $this->{$method}("/movies/tmdb/{$movie->tmdb_id}/{$fact}", ['source' => 'movie']);
    expect(factsAbout($user, $movie))->not->toBe($before);

    $this->travel(5)->seconds();
    $this->post("/movies/tmdb/{$movie->tmdb_id}/undo", ['action' => $action])->assertSessionHasNoErrors();

    expect(factsAbout($user, $movie))->toBe($before);
})->with('actions');

test('Undo fails once the facts have changed since the action, and leaves them alone', function () {
    $this->travelTo('2026-11-06 12:00:00');
    Queue::fake([RecordAnalyticsEvent::class]);
    $facts = UserMovie::factory()->create(['watchlisted_at' => '2026-10-02 09:00:00']);
    $this->actingAs($facts->user);

    $this->put("/movies/tmdb/{$facts->movie->tmdb_id}/watched", ['source' => 'movie']);
    $facts->refresh()->update(['watched_at' => '2026-11-06 12:30:00']);

    $this->post("/movies/tmdb/{$facts->movie->tmdb_id}/undo", ['action' => 'mark_as_watched'])
        ->assertSessionHasErrors(['undo' => "That can't be undone any more"]);

    expect(factsAbout($facts->user, $facts->movie))->toBe([['watchlisted_at' => null, 'watched_at' => '2026-11-06 12:30:00']])
        ->and(Queue::pushed(RecordAnalyticsEvent::class)->pluck('name')->all())->toBe(['movie_marked_watched']);
});

test('Undo works once: a second Undo of the same action fails', function () {
    $user = User::factory()->create();
    $movie = Movie::factory()->create();
    $this->actingAs($user);

    $this->put("/movies/tmdb/{$movie->tmdb_id}/watchlist", ['source' => 'movie']);
    $this->post("/movies/tmdb/{$movie->tmdb_id}/undo", ['action' => 'add_to_watchlist'])->assertSessionHasNoErrors();
    $this->post("/movies/tmdb/{$movie->tmdb_id}/undo", ['action' => 'add_to_watchlist'])->assertSessionHasErrors('undo');

    expect(factsAbout($user, $movie))->toBe([]);
});

test('only the latest action can be undone, and an older toast\'s Undo leaves it undoable', function () {
    $this->travelTo('2026-11-06 12:00:00');
    $user = User::factory()->create();
    [$first, $second] = Movie::factory()->count(2)->create();
    $this->actingAs($user);

    $this->put("/movies/tmdb/{$first->tmdb_id}/watchlist", ['source' => 'movie']);
    $this->put("/movies/tmdb/{$second->tmdb_id}/watched", ['source' => 'movie']);

    $this->post("/movies/tmdb/{$first->tmdb_id}/undo", ['action' => 'add_to_watchlist'])->assertSessionHasErrors('undo');
    $this->post("/movies/tmdb/{$second->tmdb_id}/undo", ['action' => 'add_to_watchlist'])->assertSessionHasErrors('undo');
    $this->post("/movies/tmdb/{$second->tmdb_id}/undo", ['action' => 'mark_as_watched'])->assertSessionHasNoErrors();

    expect(factsAbout($user, $first))->toBe([['watchlisted_at' => '2026-11-06 12:00:00', 'watched_at' => null]])
        ->and(factsAbout($user, $second))->toBe([]);
});

test('an action that changed nothing is still the latest, so an earlier action can no longer be undone', function () {
    $user = User::factory()->create();
    $movie = Movie::factory()->create();
    $this->actingAs($user);

    $this->put("/movies/tmdb/{$movie->tmdb_id}/watchlist", ['source' => 'movie']);
    $this->delete('/movies/tmdb/157336/watched', ['source' => 'watched']);

    $this->post("/movies/tmdb/{$movie->tmdb_id}/undo", ['action' => 'add_to_watchlist'])->assertSessionHasErrors('undo');
    expect(factsAbout($user, $movie))->toHaveCount(1);
});

test('undoing an action that changed nothing changes nothing and records nothing', function () {
    Queue::fake([RecordAnalyticsEvent::class]);
    $facts = UserMovie::factory()->create(['watchlisted_at' => '2026-10-02 09:00:00']);
    $this->actingAs($facts->user);

    $this->put("/movies/tmdb/{$facts->movie->tmdb_id}/watchlist", ['source' => 'movie']);
    $this->post("/movies/tmdb/{$facts->movie->tmdb_id}/undo", ['action' => 'add_to_watchlist'])->assertSessionHasNoErrors();

    expect(factsAbout($facts->user, $facts->movie))->toBe([['watchlisted_at' => '2026-10-02 09:00:00', 'watched_at' => null]]);
    Queue::assertNothingPushed();
});

test('Undo re-creates a Movie row purged since the action, by catalog id, with the facts restored', function () {
    Queue::fake([RecordAnalyticsEvent::class]);
    $facts = UserMovie::factory()->create(['watchlisted_at' => '2026-10-02 09:00:00']);
    [$user, $movie] = [$facts->user, $facts->movie];
    fakeCatalogKnowing($movie);
    $this->actingAs($user);

    $this->delete("/movies/tmdb/{$movie->tmdb_id}/watchlist", ['source' => 'watchlist']);
    $movie->delete();

    $this->post("/movies/tmdb/{$movie->tmdb_id}/undo", ['action' => 'remove_from_watchlist'])->assertSessionHasNoErrors();

    $recreated = Movie::query()->sole();
    expect($recreated->tmdb_id)->toBe($movie->tmdb_id)
        ->and(factsAbout($user, $recreated))->toBe([['watchlisted_at' => '2026-10-02 09:00:00', 'watched_at' => null]])
        ->and(Queue::pushed(RecordAnalyticsEvent::class)->map(fn (RecordAnalyticsEvent $job): array => [$job->name, $job->properties])->last())
        ->toBe(['user_movie_action_undone', ['movie_id' => $recreated->id, 'action' => 'remove_from_watchlist']]);
});

test('Undo can be tried again when re-creating a purged Movie row fails', function () {
    $facts = UserMovie::factory()->create(['watchlisted_at' => '2026-10-02 09:00:00']);
    [$user, $movie] = [$facts->user, $facts->movie];
    $this->actingAs($user)->delete("/movies/tmdb/{$movie->tmdb_id}/watchlist", ['source' => 'watchlist']);
    $movie->delete();

    $this->app->instance(CatalogSource::class, new FakeCatalogSource(failing: true));
    $this->post("/movies/tmdb/{$movie->tmdb_id}/undo", ['action' => 'remove_from_watchlist'])->assertServiceUnavailable();

    fakeCatalogKnowing($movie);
    $this->post("/movies/tmdb/{$movie->tmdb_id}/undo", ['action' => 'remove_from_watchlist'])->assertSessionHasNoErrors();

    expect(factsAbout($user, Movie::query()->sole()))->toBe([['watchlisted_at' => '2026-10-02 09:00:00', 'watched_at' => null]]);
});

test('Undo fails without asking the Catalog when the facts and the Movie row have gone since the action', function () {
    $user = User::factory()->create();
    $movie = Movie::factory()->create();
    $this->actingAs($user)->put("/movies/tmdb/{$movie->tmdb_id}/watchlist", ['source' => 'movie']);
    UserMovie::query()->delete();
    $movie->delete();
    $this->app->instance(CatalogSource::class, new FakeCatalogSource(failing: true));

    $this->post("/movies/tmdb/{$movie->tmdb_id}/undo", ['action' => 'add_to_watchlist'])->assertSessionHasErrors('undo');

    $this->assertDatabaseCount('titles', 0);
});

test('Undo restores a Removed Movie\'s facts', function () {
    $facts = UserMovie::factory()->watched()->for(Movie::factory()->removed())->create(['watched_at' => '2026-10-01 09:00:00']);
    $this->actingAs($facts->user);

    $this->delete("/movies/tmdb/{$facts->movie->tmdb_id}/watched", ['source' => 'watched']);
    $this->post("/movies/tmdb/{$facts->movie->tmdb_id}/undo", ['action' => 'clear_watched'])->assertSessionHasNoErrors();

    expect(factsAbout($facts->user, $facts->movie))->toBe([['watchlisted_at' => null, 'watched_at' => '2026-10-01 09:00:00']]);
});

test('Undo ignores dates the client sends', function () {
    $facts = UserMovie::factory()->watched()->create(['watched_at' => '2026-10-01 09:00:00']);
    $this->actingAs($facts->user);

    $this->delete("/movies/tmdb/{$facts->movie->tmdb_id}/watched", ['source' => 'watched']);
    $this->post("/movies/tmdb/{$facts->movie->tmdb_id}/undo", [
        'action' => 'clear_watched',
        'watched_at' => '2020-01-01 00:00:00',
        'before' => ['watched_at' => '2020-01-01 00:00:00'],
    ])->assertSessionHasNoErrors();

    expect(factsAbout($facts->user, $facts->movie))->toBe([['watchlisted_at' => null, 'watched_at' => '2026-10-01 09:00:00']]);
});

test('Undo never restores another user\'s action', function () {
    $movie = Movie::factory()->create();
    $someoneElse = User::factory()->create();

    $this->actingAs(User::factory()->create())->put("/movies/tmdb/{$movie->tmdb_id}/watchlist", ['source' => 'movie']);
    $this->actingAs($someoneElse)->post("/movies/tmdb/{$movie->tmdb_id}/undo", ['action' => 'add_to_watchlist'])->assertSessionHasErrors('undo');

    $this->assertDatabaseCount('user_titles', 1);
});

test('guests are sent to sign in', function () {
    $movie = Movie::factory()->create();

    $this->post("/movies/tmdb/{$movie->tmdb_id}/undo", ['action' => 'add_to_watchlist'])->assertRedirect(route('login'));
});

test('Undo needs a known action', function (array $data, string $message) {
    $movie = Movie::factory()->create();

    $this->actingAs(User::factory()->create())
        ->post("/movies/tmdb/{$movie->tmdb_id}/undo", $data)
        ->assertSessionHasErrors(['action' => $message]);
})->with([
    'missing' => [[], 'The action field is required.'],
    'unknown' => [['action' => 'delete_account'], 'The selected action is invalid.'],
]);

test('Undo shares the sixty-a-minute action limit', function () {
    $this->freezeTime();
    $movie = Movie::factory()->create();
    $this->actingAs(User::factory()->create());

    for ($attempt = 1; $attempt <= 60; $attempt++) {
        $this->put("/movies/tmdb/{$movie->tmdb_id}/watchlist", ['source' => 'movie'])->assertRedirect();
    }

    $this->post("/movies/tmdb/{$movie->tmdb_id}/undo", ['action' => 'add_to_watchlist'])->assertTooManyRequests();
});

test('an action can be undone for thirty minutes', function (int $minutes, bool $undoable) {
    $user = User::factory()->create();
    $movie = Movie::factory()->create();
    $this->actingAs($user)->put("/movies/tmdb/{$movie->tmdb_id}/watchlist", ['source' => 'movie']);

    $this->travel($minutes)->minutes();
    $response = $this->post("/movies/tmdb/{$movie->tmdb_id}/undo", ['action' => 'add_to_watchlist']);

    $undoable ? $response->assertSessionHasNoErrors() : $response->assertSessionHasErrors('undo');
    expect(factsAbout($user, $movie))->toHaveCount($undoable ? 0 : 1);
})->with([
    'at thirty minutes' => [30, true],
    'after thirty minutes' => [31, false],
]);

test('Undo restores Movie dates without restoring or rejecting a changed User rating', function (string $method, string $fact, string $action, ?string $watchlistedAt, ?string $watchedAt, ?int $rating) {
    $this->travelTo('2026-11-09 12:00:00');
    $user = User::factory()->create();
    $movie = Movie::factory()->create();
    UserMovie::factory()->for($user)->for($movie)->create([
        'watchlisted_at' => $watchlistedAt, 'watched_at' => $watchedAt, 'rating' => 5,
    ]);
    $this->actingAs($user);
    $this->{$method}("/movies/tmdb/{$movie->tmdb_id}/{$fact}", ['source' => 'movie'])->assertRedirect()->assertSessionHasNoErrors();
    $this->assertDatabaseHas('user_titles', ['user_id' => $user->id, 'title_id' => $movie->id, 'rating' => 5]);
    $this->putJson(route('titles.rating.update', ['title' => $movie->id]), ['rating' => $rating])->assertNoContent();

    $this->post(route('movies.undo', ['tmdbId' => $movie->tmdb_id]), ['action' => $action])
        ->assertRedirect()->assertSessionHasNoErrors();

    if ($rating === null && $watchlistedAt === null && $watchedAt === null) {
        $this->assertDatabaseMissing('user_titles', ['user_id' => $user->id, 'title_id' => $movie->id]);
    } else {
        $this->assertDatabaseHas('user_titles', [
            'user_id' => $user->id, 'title_id' => $movie->id, 'rating' => $rating,
            'watchlisted_at' => $watchlistedAt, 'watched_at' => $watchedAt,
        ]);
    }
})->with([
    'add to Watchlist' => ['put', 'watchlist', 'add_to_watchlist', null, null],
    'remove from Watchlist' => ['delete', 'watchlist', 'remove_from_watchlist', '2026-11-01 12:00:00', null],
    'mark Watched' => ['put', 'watched', 'mark_as_watched', null, null],
    'clear Watched' => ['delete', 'watched', 'clear_watched', null, '2026-11-02 12:00:00'],
])->with(['changed rating' => 9, 'cleared rating' => null]);
