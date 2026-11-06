<?php

use App\Actions\RecordEvent;
use App\Jobs\RecordAnalyticsEvent;
use App\Models\User;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Queue;

test('signed-in events are queued on default only after the response', function () {
    $user = User::factory()->create();
    Queue::fake([RecordAnalyticsEvent::class]);
    $this->travelTo('2026-11-05 12:00:00');

    app(RecordEvent::class)->handle($user, 'movie_viewed', ['movie_id' => 42, 'referrer' => 'discover']);

    Queue::assertNothingPushed();

    $this->travel(1)->minute();
    app()->terminate();

    Queue::assertPushedOn('default', RecordAnalyticsEvent::class, function (RecordAnalyticsEvent $job) use ($user) {
        expect($job->uuid)->toBeUuid();
        expect($job->userId)->toBe($user->id);
        expect($job->name)->toBe('movie_viewed');
        expect($job->properties)->toBe(['movie_id' => 42, 'referrer' => 'discover']);
        expect($job->occurredAt)->toBe('2026-11-05 12:00:00');
        expect(serialize($job))->not->toContain($user->email, $user->password);

        return true;
    });
});

test('anonymous visitors are not recorded', function () {
    Queue::fake([RecordAnalyticsEvent::class]);

    app(RecordEvent::class)->handle(null, 'movie_viewed', ['movie_id' => 42]);
    app()->terminate();

    Queue::assertNothingPushed();
});

test('an enqueue failure is swallowed and reported', function () {
    $user = User::factory()->create();
    Exceptions::fake();
    config(['queue.default' => 'unavailable']);

    app(RecordEvent::class)->handle($user, 'movie_viewed', ['movie_id' => 42]);
    app()->terminate();

    Exceptions::assertReported(fn (InvalidArgumentException $exception) => $exception->getMessage() === 'The [unavailable] queue connection has not been configured.');
});

test('an event is queued once, however many times the app terminates afterwards', function () {
    Queue::fake([RecordAnalyticsEvent::class]);

    app(RecordEvent::class)->handle(User::factory()->create(), 'movie_viewed', ['movie_id' => 42]);
    app()->terminate();
    app()->terminate();

    Queue::assertPushed(RecordAnalyticsEvent::class, 1);
});
