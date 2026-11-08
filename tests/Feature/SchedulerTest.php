<?php

use App\Jobs\BuildDiscover;
use App\Jobs\CheckOverdueRefreshes;
use App\Jobs\CheckRatingDatasetFreshness;
use App\Jobs\CleanUpCatalog;
use App\Jobs\ClearExpiredPasswordResets;
use App\Jobs\PlanTitleRefreshes;
use App\Jobs\PruneFailedJobs;
use App\Jobs\PruneUnverifiedAccounts;
use App\Jobs\RefreshGenres;
use App\Jobs\RollUpEvents;
use App\Jobs\SyncRatingDataset;
use App\Services\Ratings\Enums\RatingSourceName;
use Carbon\CarbonInterval;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

/** Whether a queued job was delayed by at least a minute and no more than the 15 minutes a job's delay may be. */
function delayedWithinLimit(?DateInterval $delay): bool
{
    $minutes = $delay === null ? 0 : CarbonInterval::instance($delay)->totalMinutes;

    return $minutes >= 1 && $minutes <= 15;
}

test('at 05:00 UTC the scheduler dispatches each Rating dataset sync, a delayed freshness check, the Discover build, the refresh planner, the catalog cleanup and a delayed overdue check', function () {
    $this->travelTo('2026-11-06 05:00:00');
    Queue::fake();

    $this->artisan('schedule:run')->assertSuccessful();

    Queue::assertPushedOn('imports', SyncRatingDataset::class, fn (SyncRatingDataset $job) => $job->source === RatingSourceName::Imdb);
    Queue::assertPushed(CheckRatingDatasetFreshness::class, fn (CheckRatingDatasetFreshness $job) => delayedWithinLimit($job->delay));
    Queue::assertPushed(BuildDiscover::class);
    Queue::assertPushed(PlanTitleRefreshes::class, fn (PlanTitleRefreshes $job) => $job->afterId === 0);
    Queue::assertPushed(CleanUpCatalog::class);
    Queue::assertPushed(RollUpEvents::class);
    Queue::assertPushed(CheckOverdueRefreshes::class, fn (CheckOverdueRefreshes $job) => delayedWithinLimit($job->delay));
    Queue::assertNotPushed(RefreshGenres::class);
});

test('on Mondays the 05:00 UTC slot also dispatches the genre refresh', function () {
    $this->travelTo('2026-11-09 05:00:00');
    Queue::fake();

    $this->artisan('schedule:run')->assertSuccessful();

    Queue::assertPushed(RefreshGenres::class);
});

test('nothing is dispatched outside the 05:00 UTC slot', function () {
    $this->travelTo('2026-11-09 05:01:00');
    Queue::fake();

    $this->artisan('schedule:run')->assertSuccessful();

    Queue::assertNothingPushed();
});

test('a switched-off source is not dispatched', function () {
    $this->travelTo('2026-11-06 05:00:00');
    Queue::fake();
    config(['ratings.sources.imdb.enabled' => false]);

    $this->artisan('schedule:run')->assertSuccessful();

    Queue::assertNotPushed(SyncRatingDataset::class);
});

test('every scheduled task runs once, on one server, in UTC', function () {
    $events = collect(app(Schedule::class)->events());

    expect($events)->not->toBeEmpty()
        ->and($events->every(fn (Event $event) => $event->onOneServer && $event->timezone === 'UTC'))->toBeTrue()
        ->and($events->map(fn (Event $event) => $event->mutexName())->duplicates())->toBeEmpty();
});

test('at 05:00 UTC the scheduler dispatches the unverified-account cleanup', function () {
    $this->travelTo('2026-11-06 05:00:00');
    Queue::fake();

    $this->artisan('schedule:run')->assertSuccessful();

    Queue::assertPushedOn('default', PruneUnverifiedAccounts::class);
});

test('at 05:00 UTC the scheduler dispatches the password-reset and failed-job cleanups', function () {
    $this->travelTo('2026-11-06 05:00:00');
    Queue::fake();

    $this->artisan('schedule:run')->assertSuccessful();

    Queue::assertPushedOn('default', ClearExpiredPasswordResets::class);
    Queue::assertPushedOn('default', PruneFailedJobs::class);
});

test('the password-reset cleanup deletes tokens older than 60 minutes', function () {
    DB::table('password_reset_tokens')->insert([
        ['email' => 'expired@example.com', 'token' => 'x', 'created_at' => now()->subMinutes(61)],
        ['email' => 'fresh@example.com', 'token' => 'x', 'created_at' => now()->subMinutes(59)],
    ]);

    ClearExpiredPasswordResets::dispatchSync();

    expect(DB::table('password_reset_tokens')->pluck('email')->all())->toBe(['fresh@example.com']);
});

test('the failed-job cleanup deletes failed jobs older than 7 days', function () {
    foreach ([['old', now()->subHours(169)], ['recent', now()->subHours(167)]] as [$uuid, $failedAt]) {
        DB::table('failed_jobs')->insert(['uuid' => $uuid, 'connection' => 'sync', 'queue' => 'default', 'payload' => '{}', 'exception' => 'e', 'failed_at' => $failedAt]);
    }

    PruneFailedJobs::dispatchSync();

    expect(DB::table('failed_jobs')->pluck('uuid')->all())->toBe(['recent']);
});
