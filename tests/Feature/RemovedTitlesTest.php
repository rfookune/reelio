<?php

use App\Enums\Surface;
use App\Http\Exceptions\TitleRemoved;
use App\Models\Movie;
use App\Models\User;
use App\Models\UserMovie;
use Inertia\Testing\AssertableInertia as Assert;

test('Removed Movies are a separate unpaginated group with only the viewers own dates', function (string $list, string $expectedDate) {
    $user = User::factory()->create();
    $removed = Movie::factory()->removed()->count(49)->create()->map(fn (Movie $movie): UserMovie => UserMovie::factory()->for($user)->for($movie)->create([
        'watchlisted_at' => '2026-10-02 12:00:00', 'watched_at' => '2026-10-01 12:00:00',
    ]));
    $available = UserMovie::factory()->for($user)->create(['watched_at' => '2026-10-03']);
    UserMovie::factory()->for(Movie::factory()->removed())->create(['watched_at' => '2026-10-04']);
    $this->actingAs($user);

    foreach (['title', 'newest-release'] as $sort) {
        $this->get(route($list, ['sort' => $sort]))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('unavailableMovies', 49)
            ->where('unavailableMovies.0', [
                'tmdbId' => $removed[0]->movie->tmdb_id,
                'title' => 'Removed movie',
                'year' => null,
                'posterPath' => null,
                'ratings' => [],
                'href' => $removed[0]->movie->canonicalUrl(Surface::from($list)),
                'date' => $expectedDate,
            ])
            ->has('titles.data', 1)
            ->where('titles.data.0.tmdbId', $available->movie->tmdb_id)
            ->where('titles.next_cursor', null)
            ->has('userMovies', 50)
            ->where('trending', []));
    }
})->with(['Watchlist' => ['watchlist', '2026-10-02T12:00:00.000000Z'], 'Watched' => ['watched', '2026-10-01T12:00:00.000000Z']]);

test('the 410 page includes only the signed-in viewers facts and no Movie details', function () {
    $movie = Movie::factory()->removed()->create();
    $user = User::factory()->create();
    UserMovie::factory()->for($user)->for($movie)->create(['watchlisted_at' => '2026-10-02', 'watched_at' => '2026-10-01']);
    UserMovie::factory()->for($movie)->watched()->create(['watched_at' => '2025-01-01']);

    $this->actingAs($user)->get($movie->canonicalUrl())->assertGone()->assertHeader('X-Robots-Tag', 'noindex')
        ->assertInertia(fn (Assert $page) => $page->component('Error')
            ->where('exceptionClass', TitleRemoved::class)
            ->where('data.tmdbId', $movie->tmdb_id)
            ->where('data.userMovies', [$movie->tmdb_id => ['watchlisted' => true, 'watched' => true, 'watchedDate' => '2026-10-01T00:00:00.000000Z']])
            ->missing('movie'));
});

test('the 410 page never exposes another users facts', function (bool $signedIn) {
    $movie = Movie::factory()->removed()->create();
    UserMovie::factory()->for($movie)->watched()->create();
    if ($signedIn) {
        $this->actingAs(User::factory()->create());
    }

    $this->get($movie->canonicalUrl())->assertGone()->assertInertia(fn (Assert $page) => $page
        ->where('data.userMovies.'.$movie->tmdb_id, ['watchlisted' => false, 'watched' => false, 'watchedDate' => null]));
})->with(['guest' => false, 'another user' => true]);

test('clearing a Removed Movie hides it from that list and Undo restores its original date', function (string $list, string $action, string $expectedDate) {
    $facts = UserMovie::factory()->for(Movie::factory()->removed())->create([
        'watchlisted_at' => '2026-10-02 12:00:00', 'watched_at' => '2026-10-01 12:00:00',
    ]);
    $this->actingAs($facts->user)->from(route($list));

    $this->delete(route('movies.'.$list.'.destroy', ['tmdbId' => $facts->movie->tmdb_id]), ['source' => $list])->assertRedirect(route($list));
    $this->get(route($list))->assertOk()->assertInertia(fn (Assert $page) => $page->where('unavailableMovies', []));
    $this->post(route('movies.undo', ['tmdbId' => $facts->movie->tmdb_id]), ['action' => $action])->assertSessionHasNoErrors()->assertRedirect(route($list));
    $this->get(route($list))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('unavailableMovies', 1)->where('unavailableMovies.0.date', $expectedDate));
})->with([
    'Watchlist' => ['watchlist', 'remove_from_watchlist', '2026-10-02T12:00:00.000000Z'],
    'Watched' => ['watched', 'clear_watched', '2026-10-01T12:00:00.000000Z'],
]);
