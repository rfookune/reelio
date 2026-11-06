<?php

use App\Actions\AddToWatchlist;
use App\Models\Movie;
use App\Models\User;
use App\Models\UserMovie;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

/** Logs the given user in through the sign-in form, as the guest would. */
function logInThroughForm(User $user): TestResponse
{
    return test()->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);
}

test('a guest who saves a Movie, then logs in, lands back on its page with it saved and the toast', function () {
    $this->travelTo('2026-11-06 12:00:00');
    $movie = Movie::factory()->create(['tmdb_id' => 157336]);
    fakeCatalogKnowing($movie);
    $user = User::factory()->create();

    $this->get($movie->canonicalUrl())->assertOk();
    $this->put('/movies/tmdb/157336/watchlist', ['source' => 'movie'])->assertRedirect(route('login'));
    $this->assertDatabaseCount('user_movies', 0);

    logInThroughForm($user)
        ->assertRedirect($movie->canonicalUrl())
        ->assertInertiaFlash('userMovieAction', [
            'tmdbId' => 157336,
            'action' => 'add_to_watchlist',
            'previous' => ['watchlisted' => false, 'watched' => false, 'watchedDate' => null],
        ]);

    $this->assertDatabaseHas('user_movies', ['user_id' => $user->id, 'movie_id' => $movie->id, 'watchlisted_at' => '2026-11-06 12:00:00']);
});

test('a guest who marks a Movie as watched, then registers, has it marked before verifying their email', function () {
    $this->travelTo('2026-11-06 12:00:00');
    $movie = Movie::factory()->create();
    fakeCatalogKnowing($movie);

    $this->get($movie->canonicalUrl());
    $this->put("/movies/tmdb/{$movie->tmdb_id}/watched", ['source' => 'movie'])->assertRedirect(route('login'));

    $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])
        ->assertRedirect($movie->canonicalUrl())
        ->assertInertiaFlash('userMovieAction.action', 'mark_as_watched');

    $user = User::query()->sole();
    expect($user->hasVerifiedEmail())->toBeFalse();
    $this->assertDatabaseHas('user_movies', ['user_id' => $user->id, 'movie_id' => $movie->id, 'watched_at' => '2026-11-06 12:00:00']);
});

test('a guest who takes a Movie off a stale Watchlist page, then logs in, has it taken off', function () {
    $facts = UserMovie::factory()->create();

    $this->delete("/movies/tmdb/{$facts->movie->tmdb_id}/watchlist", ['source' => 'watchlist'])->assertRedirect(route('login'));
    logInThroughForm($facts->user)->assertInertiaFlash('userMovieAction.previous.watchlisted', true);

    $this->assertModelMissing($facts);
});

test('sign-in returns to the page last loaded by an Inertia visit, whatever page the request names', function () {
    $movie = Movie::factory()->create();
    $other = Movie::factory()->create();
    fakeCatalogKnowing($movie, $other);

    $version = (string) $this->get('/')->viewData('page')['version'];
    $this->get($movie->canonicalUrl(), ['X-Inertia' => 'true', 'X-Inertia-Version' => $version, 'X-Requested-With' => 'XMLHttpRequest'])
        ->assertOk()
        ->assertHeader('X-Inertia');
    $this->get($other->canonicalUrl(), ['Purpose' => 'prefetch'])->assertOk();
    $this->get('/movies/tmdb/'.$other->tmdb_id)->assertRedirect($other->canonicalUrl());
    $this->get('/movies/missing-999999')->assertNotFound();
    $this->get(route('register'))->assertOk();

    $this->put("/movies/tmdb/{$movie->tmdb_id}/watchlist", ['source' => 'movie', 'redirect' => $other->canonicalUrl()], ['Referer' => $other->canonicalUrl()]);

    logInThroughForm(User::factory()->create())->assertRedirect($movie->canonicalUrl());
});

test('a guest who has loaded no page returns to Discover', function () {
    $movie = Movie::factory()->create();

    $this->put("/movies/tmdb/{$movie->tmdb_id}/watchlist", ['source' => 'movie']);

    logInThroughForm(User::factory()->create())->assertRedirect(route('home'));
});

test('the intended-URL guard sends a guest whose recorded page is not a local path to Discover', function (string $recorded) {
    $movie = Movie::factory()->create();

    $this->withSession(['guest.last_page' => $recorded])->put("/movies/tmdb/{$movie->tmdb_id}/watchlist", ['source' => 'movie']);

    logInThroughForm(User::factory()->create())->assertRedirect(route('home'));
})->with([
    'protocol-relative' => '//evil.com',
    'backslash' => '/\evil.com',
    'absolute' => 'https://evil.com/',
    'tab' => "/\t/evil.com",
    'trailing newline' => "/watchlist\n",
]);

test('a pending action waits 30 minutes for sign-in, then expires', function (int $minutes, int $saved) {
    $this->travelTo('2026-11-06 12:00:00');
    $movie = Movie::factory()->create();

    $this->put("/movies/tmdb/{$movie->tmdb_id}/watchlist", ['source' => 'movie']);
    $this->travel($minutes)->minutes();
    logInThroughForm(User::factory()->create());

    $this->assertDatabaseCount('user_movies', $saved);
})->with([
    'at 30 minutes' => [30, 1],
    'after 30 minutes' => [31, 0],
]);

test('a pending action is applied once', function () {
    $movie = Movie::factory()->create();
    $user = User::factory()->create();

    $this->put("/movies/tmdb/{$movie->tmdb_id}/watchlist", ['source' => 'movie']);
    logInThroughForm($user);
    $this->get('/')->assertInertia(fn (Assert $page) => $page->hasFlash('userMovieAction'));
    $this->delete("/movies/tmdb/{$movie->tmdb_id}/watchlist", ['source' => 'movie']);
    auth()->guard('web')->logout();

    logInThroughForm($user)->assertInertiaFlashMissing('userMovieAction');
    $this->assertDatabaseCount('user_movies', 0);
});

test('only the latest pending action is applied', function () {
    $first = Movie::factory()->create();
    $latest = Movie::factory()->create();
    $user = User::factory()->create();

    $this->put("/movies/tmdb/{$first->tmdb_id}/watchlist", ['source' => 'movie']);
    $this->put("/movies/tmdb/{$latest->tmdb_id}/watched", ['source' => 'movie']);
    logInThroughForm($user);

    $this->assertDatabaseCount('user_movies', 1);
    $this->assertDatabaseHas('user_movies', ['user_id' => $user->id, 'movie_id' => $latest->id, 'watchlisted_at' => null]);
});

test('a pending action that cannot be applied still signs the user in, with an error toast', function () {
    fakeCatalogKnowing();
    $user = User::factory()->create();

    $this->get('/');
    $this->put('/movies/tmdb/999999/watchlist', ['source' => 'search'])->assertRedirect(route('login'));

    logInThroughForm($user)
        ->assertRedirect(route('home'))
        ->assertInertiaFlash('toast', ['type' => 'error', 'message' => "Couldn't save that. Try again."])
        ->assertInertiaFlashMissing('userMovieAction');
    $this->assertAuthenticatedAs($user);
    $this->assertDatabaseCount('user_movies', 0);
});

test('a guest who signs in through the two-factor challenge has their pending action applied', function () {
    $movie = Movie::factory()->create();
    fakeCatalogKnowing($movie);
    $user = User::factory()->withTwoFactor()->create();

    $this->get($movie->canonicalUrl());
    $this->put("/movies/tmdb/{$movie->tmdb_id}/watchlist", ['source' => 'movie']);
    logInThroughForm($user)->assertRedirect(route('two-factor.login'));
    $this->assertDatabaseCount('user_movies', 0);

    $this->post(route('two-factor.login.store'), ['recovery_code' => $user->recoveryCodes()[0]])
        ->assertRedirect($movie->canonicalUrl())
        ->assertInertiaFlash('userMovieAction.action', 'add_to_watchlist');
    $this->assertDatabaseHas('user_movies', ['user_id' => $user->id, 'movie_id' => $movie->id]);
});

test('a sign-in by remember-me cookie on a page load applies nothing, so no GET changes data', function () {
    $movie = Movie::factory()->create();
    fakeCatalogKnowing($movie);
    $user = User::factory()->create(['remember_token' => 'remembered']);

    $this->put("/movies/tmdb/{$movie->tmdb_id}/watchlist", ['source' => 'movie']);
    $this->withCookie(auth()->guard('web')->getRecallerName(), "{$user->id}|remembered|{$user->getAuthPassword()}")
        ->get($movie->canonicalUrl())
        ->assertOk();

    $this->assertAuthenticatedAs($user);
    $this->assertDatabaseCount('user_movies', 0);
});

test('a pending action that fails unexpectedly still signs the user in, and the failure is reported', function () {
    Exceptions::fake();
    $this->mock(AddToWatchlist::class)->shouldReceive('handle')->andThrow(new RuntimeException('Database unavailable'));
    $movie = Movie::factory()->create();
    $user = User::factory()->create();

    $this->put("/movies/tmdb/{$movie->tmdb_id}/watchlist", ['source' => 'movie']);
    logInThroughForm($user)->assertInertiaFlash('toast.message', "Couldn't save that. Try again.");

    $this->assertAuthenticatedAs($user);
    Exceptions::assertReported(RuntimeException::class);
});

test('a guest\'s action needs a known source and stores nothing without one', function () {
    $movie = Movie::factory()->create();

    $this->put("/movies/tmdb/{$movie->tmdb_id}/watchlist", ['source' => 'homepage'])->assertSessionHasErrors('source');
    logInThroughForm(User::factory()->create())->assertInertiaFlashMissing('userMovieAction');

    $this->assertDatabaseCount('user_movies', 0);
});

test('the applied action can be undone from its toast', function () {
    $movie = Movie::factory()->create();
    $user = User::factory()->create();

    $this->put("/movies/tmdb/{$movie->tmdb_id}/watchlist", ['source' => 'movie']);
    logInThroughForm($user);

    $this->post("/movies/tmdb/{$movie->tmdb_id}/undo", ['action' => 'add_to_watchlist'])->assertSessionHasNoErrors();
    $this->assertDatabaseCount('user_movies', 0);
});

test('storing a pending action counts in the guest\'s shared action limit', function () {
    $this->freezeTime();
    $movie = Movie::factory()->create();

    for ($attempt = 1; $attempt <= 60; $attempt++) {
        $this->put("/movies/tmdb/{$movie->tmdb_id}/watchlist", ['source' => 'movie'])->assertRedirect(route('login'));
    }

    $this->put("/movies/tmdb/{$movie->tmdb_id}/watched", ['source' => 'movie'])->assertTooManyRequests()->assertHeader('Retry-After', '60');
});
