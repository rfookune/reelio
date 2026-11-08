<?php

use App\Jobs\RollUpEvents;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->travelTo('2026-11-07 05:00:00');
});

/** @param  array<string, bool|int|string|null>  $properties */
function seedEvent(User $user, string $name, string $occurredAt, array $properties = []): void
{
    DB::table('events')->insert([
        'uuid' => (string) Str::uuid(),
        'user_id' => $user->id,
        'name' => $name,
        'properties' => json_encode((object) $properties),
        'occurred_at' => $occurredAt,
    ]);
}

test('events older than 13 months become one anonymous count per day and event name, and are deleted', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    seedEvent($user, 'movie_viewed', '2025-10-01 03:00:00', ['movie_id' => 1, 'referrer' => 'discover']);
    seedEvent($other, 'movie_viewed', '2025-10-01 22:00:00', ['movie_id' => 2, 'referrer' => 'search']);
    seedEvent($user, 'title_searched', '2025-10-01 09:00:00', ['query_length' => 4, 'result_count' => 9]);
    seedEvent($user, 'movie_viewed', '2025-10-02 09:00:00', ['movie_id' => 1, 'referrer' => 'discover']);

    RollUpEvents::dispatchSync();

    $this->assertDatabaseEmpty('events');
    expect(DB::table('event_daily_counts')->orderBy('day')->orderBy('name')->get(['day', 'name', 'count', 'pool_size_total'])->map(fn ($row) => (array) $row)->all())->toBe([
        ['day' => '2025-10-01', 'name' => 'movie_viewed', 'count' => 2, 'pool_size_total' => null],
        ['day' => '2025-10-01', 'name' => 'title_searched', 'count' => 1, 'pool_size_total' => null],
        ['day' => '2025-10-02', 'name' => 'movie_viewed', 'count' => 1, 'pool_size_total' => null],
    ]);
});

test('movie_picker_used keeps the total of its pool sizes', function () {
    $user = User::factory()->create();
    seedEvent($user, 'movie_picker_used', '2025-10-01 03:00:00', ['pool_size' => 4]);
    seedEvent($user, 'movie_picker_used', '2025-10-01 04:00:00', ['pool_size' => 7]);

    RollUpEvents::dispatchSync();

    $this->assertDatabaseHas('event_daily_counts', ['day' => '2025-10-01', 'name' => 'movie_picker_used', 'count' => 2, 'pool_size_total' => 11]);
});

test('an event exactly 13 months old is kept and one a second older is rolled up', function () {
    $user = User::factory()->create();
    seedEvent($user, 'movie_viewed', '2025-10-07 05:00:00');
    seedEvent($user, 'movie_viewed', '2025-10-07 04:59:59');

    RollUpEvents::dispatchSync();

    expect(DB::table('events')->pluck('occurred_at')->all())->toBe(['2025-10-07 05:00:00'])
        ->and((int) DB::table('event_daily_counts')->where('day', '2025-10-07')->value('count'))->toBe(1);
});

test('a later run adds to a day it already rolled up', function () {
    $user = User::factory()->create();
    seedEvent($user, 'movie_picker_used', '2025-10-01 03:00:00', ['pool_size' => 4]);
    RollUpEvents::dispatchSync();
    seedEvent($user, 'movie_picker_used', '2025-10-01 23:00:00', ['pool_size' => 6]);

    RollUpEvents::dispatchSync();

    $this->assertDatabaseCount('event_daily_counts', 1);
    $this->assertDatabaseHas('event_daily_counts', ['name' => 'movie_picker_used', 'count' => 2, 'pool_size_total' => 10]);
});

test('with nothing old enough it changes nothing', function () {
    seedEvent(User::factory()->create(), 'movie_viewed', '2026-11-01 03:00:00');

    RollUpEvents::dispatchSync();

    $this->assertDatabaseCount('events', 1);
    $this->assertDatabaseEmpty('event_daily_counts');
});

test('a picker event with a malformed pool size is counted and does not stop the roll-up', function () {
    $user = User::factory()->create();
    seedEvent($user, 'movie_picker_used', '2025-10-01 03:00:00', ['pool_size' => 'many']);
    seedEvent($user, 'movie_picker_used', '2025-10-01 04:00:00', ['pool_size' => 5]);

    RollUpEvents::dispatchSync();

    $this->assertDatabaseHas('event_daily_counts', ['name' => 'movie_picker_used', 'count' => 2, 'pool_size_total' => 5]);
});

test('TV bulk events roll up as action counts and discard Episode totals', function () {
    $user = User::factory()->create();
    seedEvent($user, 'season_marked_watched', '2025-10-01 03:00:00', ['tv_show_id' => 1, 'season_id' => 2, 'episode_count' => 4]);
    seedEvent($user, 'season_marked_watched', '2025-10-01 04:00:00', ['tv_show_id' => 1, 'season_id' => 3, 'episode_count' => 7]);
    seedEvent($user, 'title_searched', '2025-10-01 05:00:00', ['query_length' => 5, 'result_count' => 12]);

    RollUpEvents::dispatchSync();

    $this->assertDatabaseEmpty('events');
    $this->assertDatabaseHas('event_daily_counts', ['name' => 'season_marked_watched', 'count' => 2, 'pool_size_total' => null]);
    $this->assertDatabaseHas('event_daily_counts', ['name' => 'title_searched', 'count' => 1, 'pool_size_total' => null]);
});
