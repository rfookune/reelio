<?php

use App\Jobs\RecordAnalyticsEvent;
use App\Models\Movie;
use App\Models\User;
use App\Models\UserMovie;
use App\Services\Catalog\Sources\CatalogSource;
use App\Services\Catalog\Sources\FakeCatalogSource;
use App\Services\Catalog\Sources\MovieDetails;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

test('a signed-in user adds a Movie to their Watchlist by catalog id', function () {
    $this->travelTo('2026-11-06 12:00:00');
    Queue::fake([RecordAnalyticsEvent::class]);
    $user = User::factory()->create();
    $movie = Movie::factory()->create(['tmdb_id' => 157336]);

    $this->actingAs($user)
        ->from($movie->canonicalUrl())
        ->put('/movies/tmdb/157336/watchlist', ['source' => 'movie'])
        ->assertRedirect($movie->canonicalUrl());

    $this->assertDatabaseHas('user_movies', [
        'user_id' => $user->id,
        'movie_id' => $movie->id,
        'watchlisted_at' => '2026-11-06 12:00:00',
        'watched_at' => null,
    ]);
    Queue::assertPushed(RecordAnalyticsEvent::class, fn (RecordAnalyticsEvent $job) => $job->userId === $user->id
        && $job->name === 'movie_watchlisted'
        && $job->properties === ['movie_id' => $movie->id, 'source' => 'movie']);
    Queue::assertPushed(RecordAnalyticsEvent::class, 1);
});

test('re-adding a Movie keeps its original Date added and records no second event', function () {
    Queue::fake([RecordAnalyticsEvent::class]);
    $facts = UserMovie::factory()->create(['watchlisted_at' => '2026-10-01 09:00:00']);

    $this->actingAs($facts->user)
        ->put("/movies/tmdb/{$facts->movie->tmdb_id}/watchlist", ['source' => 'movie'])
        ->assertRedirect();

    expect($facts->fresh()->watchlisted_at->toDateTimeString())->toBe('2026-10-01 09:00:00');
    $this->assertDatabaseCount('user_movies', 1);
    Queue::assertNothingPushed();
});

test('adding a Watched Movie keeps its Watched date', function () {
    $this->travelTo('2026-11-06 12:00:00');
    $facts = UserMovie::factory()->watched()->create(['watched_at' => '2026-10-01 09:00:00']);

    $this->actingAs($facts->user)->put("/movies/tmdb/{$facts->movie->tmdb_id}/watchlist", ['source' => 'movie']);

    $this->assertDatabaseHas('user_movies', ['id' => $facts->id, 'watchlisted_at' => '2026-11-06 12:00:00', 'watched_at' => '2026-10-01 09:00:00']);
});

test('removing a Movie that is only on the Watchlist deletes its row and records the removal', function () {
    Queue::fake([RecordAnalyticsEvent::class]);
    $facts = UserMovie::factory()->create();
    $movie = $facts->movie;

    $this->actingAs($facts->user)
        ->from($movie->canonicalUrl())
        ->delete("/movies/tmdb/{$movie->tmdb_id}/watchlist", ['source' => 'watchlist'])
        ->assertRedirect($movie->canonicalUrl());

    $this->assertDatabaseCount('user_movies', 0);
    Queue::assertPushed(RecordAnalyticsEvent::class, fn (RecordAnalyticsEvent $job) => $job->userId === $facts->user_id
        && $job->name === 'movie_unwatchlisted'
        && $job->properties === ['movie_id' => $movie->id, 'source' => 'watchlist']);
    Queue::assertPushed(RecordAnalyticsEvent::class, 1);
});

test('removing a Watched Movie from the Watchlist keeps its Watched date', function () {
    $facts = UserMovie::factory()->create(['watchlisted_at' => '2026-10-02 09:00:00', 'watched_at' => '2026-10-01 09:00:00']);

    $this->actingAs($facts->user)->delete("/movies/tmdb/{$facts->movie->tmdb_id}/watchlist", ['source' => 'movie']);

    $this->assertDatabaseHas('user_movies', ['id' => $facts->id, 'watchlisted_at' => null, 'watched_at' => '2026-10-01 09:00:00']);
});

test('removing a Movie that is not on the Watchlist changes nothing and records no event', function () {
    Queue::fake([RecordAnalyticsEvent::class]);
    $facts = UserMovie::factory()->watched()->create(['watched_at' => '2026-10-01 09:00:00']);

    $this->actingAs($facts->user)->delete("/movies/tmdb/{$facts->movie->tmdb_id}/watchlist", ['source' => 'movie'])->assertRedirect();

    $this->assertDatabaseHas('user_movies', ['id' => $facts->id, 'watchlisted_at' => null, 'watched_at' => '2026-10-01 09:00:00']);
    Queue::assertNothingPushed();
});

test('removing a catalog id with no Movie row neither asks the source nor creates a row', function () {
    $this->app->instance(CatalogSource::class, new FakeCatalogSource(failing: true));

    $this->actingAs(User::factory()->create())
        ->delete('/movies/tmdb/157336/watchlist', ['source' => 'search'])
        ->assertRedirect();

    $this->assertDatabaseCount('movies', 0);
});

test('adding a catalog id with no Movie row creates the Movie through the Catalog, then saves it', function () {
    $this->travelTo('2026-11-06 12:00:00');
    $user = User::factory()->create();
    fakeCatalogKnowing(new MovieDetails(tmdbId: 157336, title: 'Interstellar'));

    $this->actingAs($user)->put('/movies/tmdb/157336/watchlist', ['source' => 'search'])->assertRedirect();

    $movie = Movie::query()->sole();
    expect($movie->tmdb_id)->toBe(157336)
        ->and($movie->title)->toBe('Interstellar')
        ->and($movie->last_viewed_at->toDateTimeString())->toBe('2026-11-06 12:00:00');
    $this->assertDatabaseHas('user_movies', ['user_id' => $user->id, 'movie_id' => $movie->id, 'watchlisted_at' => '2026-11-06 12:00:00']);
});

test('adding a catalog id the source does not know is a 404 that saves nothing', function () {
    $this->actingAs(User::factory()->create())->put('/movies/tmdb/999999/watchlist', ['source' => 'search'])->assertNotFound();

    $this->assertDatabaseCount('movies', 0);
    $this->assertDatabaseCount('user_movies', 0);
});

test('adding a catalog id with no row while the source is down is a 503 that saves nothing', function () {
    $this->app->instance(CatalogSource::class, new FakeCatalogSource(failing: true));

    $this->actingAs(User::factory()->create())->put('/movies/tmdb/157336/watchlist', ['source' => 'search'])
        ->assertServiceUnavailable()
        ->assertHeader('Retry-After', '30');

    $this->assertDatabaseCount('movies', 0);
    $this->assertDatabaseCount('user_movies', 0);
});

test('oversized catalog ids are a 404 without contacting the source', function (string $method) {
    $this->app->instance(CatalogSource::class, new FakeCatalogSource(failing: true));

    $this->actingAs(User::factory()->create())
        ->{$method}('/movies/tmdb/999999999999999999999999999/watchlist', ['source' => 'search'])
        ->assertNotFound();
})->with(['put', 'delete']);

test('a Removed Movie cannot be added to the Watchlist', function () {
    $movie = Movie::factory()->removed()->create();

    $this->actingAs(User::factory()->create())->put("/movies/tmdb/{$movie->tmdb_id}/watchlist", ['source' => 'watchlist'])->assertGone();

    $this->assertDatabaseCount('user_movies', 0);
});

test('a Removed Movie can still be removed from the Watchlist', function () {
    $facts = UserMovie::factory()->for(Movie::factory()->removed())->create();

    $this->actingAs($facts->user)->delete("/movies/tmdb/{$facts->movie->tmdb_id}/watchlist", ['source' => 'watchlist'])->assertRedirect();

    $this->assertDatabaseCount('user_movies', 0);
});

test('guests are sent to sign in and nothing is saved', function (string $method) {
    $movie = Movie::factory()->create();

    $this->{$method}("/movies/tmdb/{$movie->tmdb_id}/watchlist", ['source' => 'movie'])->assertRedirect(route('login'));

    $this->assertDatabaseCount('user_movies', 0);
})->with(['put', 'delete']);

test('a user who has not verified their email can still save', function () {
    $user = User::factory()->unverified()->create();
    $movie = Movie::factory()->create();

    $this->actingAs($user)->put("/movies/tmdb/{$movie->tmdb_id}/watchlist", ['source' => 'movie'])->assertRedirect();

    $this->assertDatabaseHas('user_movies', ['user_id' => $user->id, 'movie_id' => $movie->id]);
});

test('removing never touches another user\'s Watchlist', function () {
    $theirs = UserMovie::factory()->create();

    $this->actingAs(User::factory()->create())
        ->delete("/movies/tmdb/{$theirs->movie->tmdb_id}/watchlist", ['source' => 'movie', 'user_id' => $theirs->user_id])
        ->assertRedirect();

    $this->assertModelExists($theirs);
});

test('adding saves to the signed-in user\'s own Watchlist, whatever user the request names', function () {
    $theirs = UserMovie::factory()->create(['watchlisted_at' => '2026-10-01 09:00:00']);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->put("/movies/tmdb/{$theirs->movie->tmdb_id}/watchlist", ['source' => 'movie', 'user_id' => $theirs->user_id])
        ->assertRedirect();

    expect($theirs->fresh()->watchlisted_at->toDateTimeString())->toBe('2026-10-01 09:00:00');
    $this->assertDatabaseHas('user_movies', ['user_id' => $user->id, 'movie_id' => $theirs->movie_id]);
});

test('an action needs a known source', function (mixed $source) {
    $user = User::factory()->create();
    $movie = Movie::factory()->create();

    $this->actingAs($user)->put("/movies/tmdb/{$movie->tmdb_id}/watchlist", ['source' => $source])->assertSessionHasErrors('source');

    $this->assertDatabaseCount('user_movies', 0);
})->with(['missing' => null, 'unknown' => 'homepage']);

test('deleting a user deletes their facts, and a Movie with facts cannot be deleted', function () {
    $facts = UserMovie::factory()->create();
    $kept = UserMovie::factory()->for($facts->movie)->create();

    $facts->user->delete();

    $this->assertModelMissing($facts);
    expect(fn () => $kept->movie->delete())->toThrow(QueryException::class);
});

test('saving a new catalog id shares the entry route\'s creation limit, while stored Movies stay savable', function () {
    $this->freezeTime();
    $user = User::factory()->create();
    $movie = Movie::factory()->create();
    $this->actingAs($user);

    for ($attempt = 1; $attempt <= 20; $attempt++) {
        $this->get('/movies/tmdb/157336')->assertNotFound();
    }

    $this->put('/movies/tmdb/157336/watchlist', ['source' => 'search'])->assertTooManyRequests()->assertHeader('Retry-After', '60');
    $this->put("/movies/tmdb/{$movie->tmdb_id}/watchlist", ['source' => 'search'])->assertRedirect();
    $this->assertDatabaseCount('movies', 1);
});

test('Watchlist actions are limited to sixty a minute per user', function () {
    $this->freezeTime();
    $user = User::factory()->create();
    $movie = Movie::factory()->create();
    $this->actingAs($user);

    for ($attempt = 1; $attempt <= 30; $attempt++) {
        $this->put("/movies/tmdb/{$movie->tmdb_id}/watchlist", ['source' => 'movie'])->assertRedirect();
        $this->delete("/movies/tmdb/{$movie->tmdb_id}/watchlist", ['source' => 'movie'])->assertRedirect();
    }

    $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10'])
        ->put("/movies/tmdb/{$movie->tmdb_id}/watchlist", ['source' => 'movie'])
        ->assertTooManyRequests()
        ->assertHeader('Retry-After', '60');
    $this->assertDatabaseCount('user_movies', 0);

    $this->actingAs(User::factory()->create())->put("/movies/tmdb/{$movie->tmdb_id}/watchlist", ['source' => 'movie'])->assertRedirect();
});

test('the Movie page carries the viewer\'s facts keyed by catalog id, and reloads only them after an action', function () {
    $facts = UserMovie::factory()->create();
    $movie = $facts->movie;
    fakeCatalogKnowing($movie);

    $this->actingAs($facts->user)->get($movie->canonicalUrl())->assertInertia(fn (Assert $page) => $page
        ->where('movie.tmdbId', $movie->tmdb_id)
        ->where("userMovies.{$movie->tmdb_id}", ['watchlisted' => true, 'watched' => false, 'watchedDate' => null])
        ->reloadOnly('userMovies', fn (Assert $reload) => $reload
            ->missing('movie')
            ->missing('whereToWatch')
            ->where("userMovies.{$movie->tmdb_id}", ['watchlisted' => true, 'watched' => false, 'watchedDate' => null])));
});

test('the Movie page shows no facts to viewers who hold none', function (bool $signedIn) {
    $movie = UserMovie::factory()->create()->movie;
    fakeCatalogKnowing($movie);

    if ($signedIn) {
        $this->actingAs(User::factory()->create());
    }

    $this->get($movie->canonicalUrl())->assertInertia(fn (Assert $page) => $page
        ->where("userMovies.{$movie->tmdb_id}", ['watchlisted' => false, 'watched' => false, 'watchedDate' => null]));
})->with(['a guest' => false, 'another user' => true]);

test('reloading only the facts after an action does not refresh the Movie', function () {
    $facts = UserMovie::factory()->create();
    $movie = $facts->movie;
    fakeCatalogKnowing($movie);
    $this->actingAs($facts->user);

    $version = $this->get($movie->canonicalUrl())->assertOk()->viewData('page')['version'];
    $movie->update(['synced_at' => now()->subDays(2)]);
    $this->app->instance(CatalogSource::class, new FakeCatalogSource);
    Cache::flush();

    $this->get($movie->canonicalUrl(), [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) $version,
        'X-Inertia-Partial-Component' => 'movies/Show',
        'X-Inertia-Partial-Data' => 'userMovies',
    ])->assertOk()->assertJsonPath("props.userMovies.{$movie->tmdb_id}.watchlisted", true);

    expect($movie->fresh()->isRemoved())->toBeFalse();
});
