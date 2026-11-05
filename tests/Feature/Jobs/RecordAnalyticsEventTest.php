<?php

use App\Jobs\RecordAnalyticsEvent;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Schema;

test('a queued event stores only its identity, actor, properties and occurrence time', function () {
    $user = User::factory()->create();
    $job = new RecordAnalyticsEvent(
        uuid: '2ab37180-1884-4328-8b4a-981d723fc247',
        userId: $user->id,
        name: 'movie_viewed',
        properties: ['movie_id' => 42, 'referrer' => 'discover'],
        occurredAt: '2026-11-05 12:00:00',
    );

    $job->handle();

    $this->assertDatabaseCount('events', 1);
    $event = DB::table('events')->sole();
    expect((array) $event)->toBe([
        'uuid' => '2ab37180-1884-4328-8b4a-981d723fc247',
        'user_id' => $user->id,
        'name' => 'movie_viewed',
        'properties' => '{"movie_id":42,"referrer":"discover"}',
        'occurred_at' => '2026-11-05 12:00:00',
    ]);
});

test('a storage failure is swallowed and reported', function () {
    $user = User::factory()->create();
    Exceptions::fake();
    Schema::drop('events');
    $job = new RecordAnalyticsEvent(
        uuid: '2ab37180-1884-4328-8b4a-981d723fc247',
        userId: $user->id,
        name: 'account_registered',
        properties: [],
        occurredAt: '2026-11-05 12:00:00',
    );

    $job->handle();

    Exceptions::assertReported(QueryException::class);
});

test('redelivering the same UUID preserves one event while distinct events remain separate', function () {
    $user = User::factory()->create();
    $job = new RecordAnalyticsEvent(
        uuid: '2ab37180-1884-4328-8b4a-981d723fc247',
        userId: $user->id,
        name: 'account_registered',
        properties: [],
        occurredAt: '2026-11-05 12:00:00',
    );
    $redeliveredJob = unserialize(serialize($job));

    $job->handle();
    $redeliveredJob->handle();
    (new RecordAnalyticsEvent(
        uuid: 'ef7d68c5-124a-476b-b07a-b84ec89d33ca',
        userId: $user->id,
        name: 'account_registered',
        properties: [],
        occurredAt: '2026-11-05 12:00:00',
    ))->handle();

    $this->assertDatabaseCount('events', 2);
    expect(DB::table('events')->where('uuid', '2ab37180-1884-4328-8b4a-981d723fc247')->value('properties'))->toBe('{}');
});
