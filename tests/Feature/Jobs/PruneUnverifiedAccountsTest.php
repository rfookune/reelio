<?php

use App\Jobs\PruneUnverifiedAccounts;
use App\Models\User;
use App\Models\UserMovie;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->travelTo('2026-11-07 05:00:00');
});

test('an unverified account with no facts is deleted 30 days after registration, with what hangs off it', function () {
    $user = User::factory()->unverified()->create(['created_at' => now()->subDays(30)]);
    DB::table('sessions')->insert(['id' => 'abc', 'user_id' => $user->id, 'payload' => '', 'last_activity' => now()->timestamp]);

    PruneUnverifiedAccounts::dispatchSync();

    expect(User::find($user->id))->toBeNull()
        ->and(DB::table('sessions')->where('id', 'abc')->exists())->toBeFalse();
});

test('an unverified account younger than 30 days is kept', function () {
    $user = User::factory()->unverified()->create(['created_at' => now()->subDays(30)->addMinute()]);

    PruneUnverifiedAccounts::dispatchSync();

    expect(User::find($user->id))->not->toBeNull();
});

test('a verified account is kept however old', function () {
    $user = User::factory()->create(['created_at' => now()->subYear()]);

    PruneUnverifiedAccounts::dispatchSync();

    expect(User::find($user->id))->not->toBeNull();
});

test('an old unverified account holding a Watchlist or Watched fact is kept', function (bool $watched) {
    $user = User::factory()->unverified()->create(['created_at' => now()->subDays(90)]);
    $facts = UserMovie::factory()->for($user);
    ($watched ? $facts->watched() : $facts)->create();

    PruneUnverifiedAccounts::dispatchSync();

    expect(User::find($user->id))->not->toBeNull()
        ->and(UserMovie::where('user_id', $user->id)->count())->toBe(1);
})->with(['on the Watchlist' => false, 'Watched' => true]);
