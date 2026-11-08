<?php

use App\Models\User;
use App\Services\Analytics\Metrics;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->travelTo('2026-11-07 05:00:00');
});

/** @param  array<string, bool|int|string|null>  $properties */
function recordedEvent(User $user, string $name, string $occurredAt, array $properties = []): void
{
    DB::table('events')->insert([
        'uuid' => (string) Str::uuid(),
        'user_id' => $user->id,
        'name' => $name,
        'properties' => json_encode((object) $properties),
        'occurred_at' => $occurredAt,
    ]);
}

test('weekly returning users are the activated users with any event in the last 7 days', function () {
    [$returned, $lapsed, $new] = User::factory()->count(3)->create();
    recordedEvent($returned, 'movie_watchlisted', '2026-10-20 10:00:00');
    recordedEvent($returned, 'movie_viewed', '2026-11-05 10:00:00');
    recordedEvent($lapsed, 'movie_watchlisted', '2026-10-20 10:00:00');
    recordedEvent($new, 'movie_watchlisted', '2026-11-05 10:00:00'); // Activated inside the window, so not counted yet.

    $this->artisan('metrics:report')
        ->expectsOutputToContain('50.0% (1 of 2 activated)')
        ->assertSuccessful();
});

test('search-to-watchlist conversion is the viewers who also watchlisted within the window', function () {
    [$converted, $browsed, $outside] = User::factory()->count(3)->create();
    recordedEvent($converted, 'movie_viewed', '2026-11-01 10:00:00');
    recordedEvent($converted, 'movie_watchlisted', '2026-11-01 10:05:00');
    recordedEvent($browsed, 'movie_viewed', '2026-11-02 10:00:00');
    recordedEvent($browsed, 'movie_watchlisted', '2026-09-01 10:00:00'); // Before the window.
    recordedEvent($outside, 'movie_viewed', '2026-09-01 10:00:00'); // Viewed before the window.
    recordedEvent($outside, 'movie_watchlisted', '2026-11-02 10:00:00');

    $this->artisan('metrics:report')
        ->expectsOutputToContain('50.0% (1 of 2 viewers)')
        ->assertSuccessful();
});

test('picker usage is the share of the last 7 days\' active users who used the picker', function () {
    [$picker, $other, $stale] = User::factory()->count(3)->create();
    recordedEvent($picker, 'movie_picker_used', '2026-11-06 10:00:00', ['pool_size' => 3]);
    recordedEvent($other, 'movie_viewed', '2026-11-06 10:00:00');
    recordedEvent($stale, 'movie_picker_used', '2026-10-01 10:00:00', ['pool_size' => 3]);

    $this->artisan('metrics:report')
        ->expectsOutputToContain('50.0% (1 of 2 active)')
        ->assertSuccessful();
});

test('the daily table counts distinct users searching, never repeat searches, and reads rolled-up days for picker use', function () {
    [$one, $two] = User::factory()->count(2)->create();
    recordedEvent($one, 'movie_searched', '2026-11-06 10:00:00', ['query_length' => 3, 'result_count' => 0]);
    recordedEvent($one, 'movie_searched', '2026-11-06 11:00:00', ['query_length' => 4, 'result_count' => 5]);
    recordedEvent($two, 'movie_searched', '2026-11-06 12:00:00', ['query_length' => 4, 'result_count' => 5]);
    recordedEvent($one, 'movie_picker_used', '2026-11-06 13:00:00', ['pool_size' => 4]);
    recordedEvent($two, 'movie_picker_used', '2026-11-06 14:00:00', ['pool_size' => 8]);
    DB::table('event_daily_counts')->insert([
        ['day' => '2026-11-04', 'name' => 'movie_picker_used', 'count' => 2, 'pool_size_total' => 9],
        ['day' => '2026-11-04', 'name' => 'movie_searched', 'count' => 5, 'pool_size_total' => null],
    ]);

    $this->artisan('metrics:report --days=7')
        ->expectsOutputToContain('2026-11-06')
        ->expectsOutputToContain('2026-11-04')
        ->assertSuccessful();

    $daily = app(Metrics::class)->daily(now()->toImmutable(), 7);

    expect($daily)->toBe([
        ['day' => '2026-11-06', 'searching_users' => 2, 'picker_uses' => 2, 'average_pool_size' => 6.0],
        ['day' => '2026-11-04', 'searching_users' => null, 'picker_uses' => 2, 'average_pool_size' => 4.5],
    ]);
});

test('with no events it reports no data instead of failing', function () {
    $this->artisan('metrics:report')
        ->expectsOutputToContain('no data')
        ->assertSuccessful();
});

test('a window under one day is refused', function () {
    $this->artisan('metrics:report --days=0')->assertFailed();
});
