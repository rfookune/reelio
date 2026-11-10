<?php

use App\Models\Movie;
use App\Models\TvShow;
use App\Models\User;
use App\Models\UserTitle;

test('a user can rate a Title without Watchlist or Watched facts', function (string $kind, int $rating) {
    $user = User::factory()->create();
    $title = $kind::factory()->create();

    $this->actingAs($user)->putJson("/titles/{$title->id}/rating", ['rating' => $rating])->assertNoContent();

    $this->assertDatabaseHas('user_titles', [
        'user_id' => $user->id, 'title_id' => $title->id,
        'watchlisted_at' => null, 'watched_at' => null, 'rating' => $rating,
    ]);
})->with([Movie::class, TvShow::class])->with([1, 10]);

test('changing and clearing a User rating preserves the other facts', function (string $kind, ?string $watchlistedAt, ?string $watchedAt) {
    $user = User::factory()->create();
    $title = $kind::factory()->create();
    UserTitle::query()->create([
        'user_id' => $user->id, 'title_id' => $title->id, 'rating' => 3,
        'watchlisted_at' => $watchlistedAt, 'watched_at' => $watchedAt,
    ]);
    $this->actingAs($user);

    $this->putJson("/titles/{$title->id}/rating", ['rating' => 8])->assertNoContent();

    $this->assertDatabaseCount('user_titles', 1);
    $this->assertDatabaseHas('user_titles', [
        'user_id' => $user->id, 'title_id' => $title->id, 'rating' => 8,
        'watchlisted_at' => $watchlistedAt, 'watched_at' => $watchedAt,
    ]);

    $this->putJson("/titles/{$title->id}/rating", ['rating' => null])->assertNoContent();

    if ($watchlistedAt === null && $watchedAt === null) {
        $this->assertDatabaseEmpty('user_titles');
    } else {
        $this->assertDatabaseHas('user_titles', [
            'user_id' => $user->id, 'title_id' => $title->id, 'rating' => null,
            'watchlisted_at' => $watchlistedAt, 'watched_at' => $watchedAt,
        ]);
    }
})->with([Movie::class, TvShow::class])->with([
    'rating only' => [null, null],
    'Watchlist' => ['2026-11-01 12:00:00', null],
    'Watched' => [null, '2026-11-02 12:00:00'],
    'both dates' => ['2026-11-01 12:00:00', '2026-11-02 12:00:00'],
]);

test('invalid User ratings are refused without changing the saved rating', function (array $payload) {
    $user = User::factory()->create();
    $movie = Movie::factory()->create();
    UserTitle::query()->create(['user_id' => $user->id, 'title_id' => $movie->id, 'rating' => 7]);

    $this->actingAs($user)->putJson("/titles/{$movie->id}/rating", $payload)
        ->assertUnprocessable()->assertInvalid('rating');

    $this->assertDatabaseHas('user_titles', ['user_id' => $user->id, 'title_id' => $movie->id, 'rating' => 7]);
})->with([
    'missing' => [[]],
    'zero' => [['rating' => 0]],
    'negative' => [['rating' => -1]],
    'too high' => [['rating' => 11]],
    'fraction' => [['rating' => 5.5]],
    'numeric string' => [['rating' => '8']],
    'text' => [['rating' => 'great']],
    'boolean' => [['rating' => true]],
    'array' => [['rating' => [8]]],
    'empty string' => [['rating' => '']],
    'whitespace' => [['rating' => '  ']],
]);

test('guests cannot set or clear a User rating or leave a pending action', function (?int $rating) {
    $title = Movie::factory()->create();

    $this->putJson("/titles/{$title->id}/rating", ['rating' => $rating])->assertUnauthorized();
    $this->put("/titles/{$title->id}/rating", ['rating' => $rating])
        ->assertRedirect(route('login'))->assertSessionMissing('user_movie_actions.pending');

    $this->assertDatabaseEmpty('user_titles');
})->with([8, null]);

test('a user can only change their own User rating', function (?int $rating) {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $title = TvShow::factory()->create();
    UserTitle::query()->create(['user_id' => $other->id, 'title_id' => $title->id, 'rating' => 4]);

    $this->actingAs($user)->putJson("/titles/{$title->id}/rating", ['rating' => $rating, 'user_id' => $other->id])
        ->assertNoContent();

    $this->assertDatabaseHas('user_titles', ['user_id' => $other->id, 'title_id' => $title->id, 'rating' => 4]);
    if ($rating === null) {
        $this->assertDatabaseMissing('user_titles', ['user_id' => $user->id, 'title_id' => $title->id]);
    } else {
        $this->assertDatabaseHas('user_titles', ['user_id' => $user->id, 'title_id' => $title->id, 'rating' => $rating]);
    }
})->with([9, null]);

test('clearing an already unrated Title leaves no empty facts row', function () {
    $title = Movie::factory()->create();

    $this->actingAs(User::factory()->create())->putJson("/titles/{$title->id}/rating", ['rating' => null])->assertNoContent();

    $this->assertDatabaseEmpty('user_titles');
});

test('rating a missing Title returns not found and writes nothing', function () {
    $this->actingAs(User::factory()->create())->putJson('/titles/999999/rating', ['rating' => 8])->assertNotFound();

    $this->assertDatabaseEmpty('user_titles');
});
