<?php

use App\Models\Movie;
use App\Models\TvShow;
use App\Models\User;
use App\Models\UserMovie;
use App\Models\UserTitle;
use Inertia\Testing\AssertableInertia as Assert;

test('Watchlist cards carry the signed-in user\'s rating, and null when unrated', function () {
    $user = User::factory()->create();
    $rated = Movie::factory()->create(['tmdb_id' => 1]);
    $unrated = Movie::factory()->create(['tmdb_id' => 2]);
    $tvShow = listedTvShow($user, 3, 'Rated show', '2025-01-01', '2026-01-01');
    foreach ([$rated, $unrated] as $movie) {
        UserMovie::factory()->for($user)->for($movie)->create(['watchlisted_at' => '2026-01-02']);
    }
    UserTitle::query()->whereBelongsTo($user)->whereBelongsTo($rated, 'title')->update(['rating' => 7]);
    UserTitle::query()->whereBelongsTo($user)->whereBelongsTo($tvShow, 'title')->update(['rating' => 1]);

    $this->actingAs($user)->get(route('watchlist'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('titles.data', fn ($cards) => collect($cards)->pluck('userRating', 'key')->all() == ['1' => 7, '2' => null, 'tv:3' => 1]));
});

test('Watched cards carry the signed-in user\'s rating, and null when unrated', function () {
    $user = User::factory()->create();
    $rated = Movie::factory()->create(['tmdb_id' => 1]);
    $unrated = Movie::factory()->create(['tmdb_id' => 2]);
    foreach ([$rated, $unrated] as $movie) {
        UserMovie::factory()->for($user)->for($movie)->watched()->create();
    }
    UserTitle::query()->whereBelongsTo($user)->whereBelongsTo($rated, 'title')->update(['rating' => 10]);

    $this->actingAs($user)->get(route('watched'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('titles.data', fn ($cards) => collect($cards)->pluck('userRating', 'key')->all() == ['1' => 10, '2' => null]));
});

test('a Watched TV Show card carries the user\'s rating', function () {
    $user = User::factory()->create();
    $tvShow = TvShow::factory()->create(['tmdb_id' => 5]);
    episodeOf($tvShow, $user, '2026-02-01');
    UserTitle::query()->create(['user_id' => $user->id, 'title_id' => $tvShow->id, 'rating' => 4]);

    $this->actingAs($user)->get(route('watched'))->assertInertia(fn (Assert $page) => $page
        ->where('titles.data.0.key', 'tv:5')->where('titles.data.0.userRating', 4));
});

test('list cards never carry another user\'s rating', function () {
    [$owner, $other] = User::factory()->count(2)->create();
    $movie = Movie::factory()->create();
    foreach ([$owner, $other] as $user) {
        UserMovie::factory()->for($user)->for($movie)->create(['watchlisted_at' => '2026-01-02']);
    }
    UserTitle::query()->whereBelongsTo($owner)->whereBelongsTo($movie, 'title')->update(['rating' => 9]);

    $this->actingAs($other)->get(route('watchlist'))->assertInertia(fn (Assert $page) => $page->where('titles.data.0.userRating', null));
    $this->actingAs($owner)->get(route('watchlist'))->assertInertia(fn (Assert $page) => $page->where('titles.data.0.userRating', 9));
});
