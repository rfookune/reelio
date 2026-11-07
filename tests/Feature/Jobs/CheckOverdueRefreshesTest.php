<?php

use App\Jobs\CheckOverdueRefreshes;
use App\Models\Movie;
use App\Models\UserMovie;
use Illuminate\Support\Facades\Log;

/** A Movie someone holds, last synced the given number of days and minutes before now. */
function heldMovieSyncedAgo(int $days, bool $watchlisted, int $minutes = 1): Movie
{
    $movie = Movie::factory()->create(['synced_at' => now()->subDays($days)->subMinutes($minutes)]);
    ($watchlisted ? UserMovie::factory() : UserMovie::factory()->watched())->for($movie)->create();

    return $movie;
}

test('the owner is alerted once about the held Movies more than 14 days past their scheduled refresh', function () {
    $this->travelTo('2026-11-06 05:00:00');
    Log::spy();
    $watchlistOverdue = heldMovieSyncedAgo(17, watchlisted: true);
    heldMovieSyncedAgo(17, watchlisted: true, minutes: 0);
    $watchedOverdue = heldMovieSyncedAgo(44, watchlisted: false);
    heldMovieSyncedAgo(44, watchlisted: false, minutes: 0);
    Movie::factory()->create(['synced_at' => now()->subDays(100)]);

    CheckOverdueRefreshes::dispatch();

    Log::shouldHaveReceived('error')->once()->withArgs(fn (string $message, array $context) => $message === 'Movies users hold are overdue for refresh.'
        && $context['count'] === 2
        && $context['movie_ids'] === [$watchedOverdue->id, $watchlistOverdue->id]);
});

test('nothing is alerted while every held Movie is within its grace', function () {
    $this->travelTo('2026-11-06 05:00:00');
    Log::spy();
    heldMovieSyncedAgo(16, watchlisted: true);

    CheckOverdueRefreshes::dispatch();

    Log::shouldNotHaveReceived('error');
});
