<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    config(['session.driver' => 'database']);
});

test('a signed-in visit stores the session against the user', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('watchlist'))->assertOk();

    expect(DB::table('sessions')->where('user_id', $user->id)->exists())->toBeTrue();
});

test('deleting a user deletes their sessions on every device and leaves other sessions alone', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    foreach ([[$user->id, 'a'], [$user->id, 'b'], [$other->id, 'c'], [null, 'd']] as [$userId, $id]) {
        DB::table('sessions')->insert(['id' => $id, 'user_id' => $userId, 'payload' => '', 'last_activity' => now()->timestamp]);
    }

    $user->delete();

    expect(DB::table('sessions')->pluck('id')->sort()->values()->all())->toBe(['c', 'd']);
});
