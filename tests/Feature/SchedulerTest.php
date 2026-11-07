<?php

use App\Jobs\BuildDiscover;
use App\Jobs\CheckOverdueRefreshes;
use App\Jobs\CheckRatingDatasetFreshness;
use App\Jobs\PlanMovieRefreshes;
use App\Jobs\RefreshGenres;
use App\Jobs\SyncRatingDataset;
use App\Services\Ratings\Enums\RatingSourceName;
use Carbon\CarbonInterval;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Queue;

/** Whether a queued job was delayed by at least a minute and no more than the 15 minutes a job's delay may be. */
function delayedWithinLimit(?DateInterval $delay): bool
{
    $minutes = $delay === null ? 0 : CarbonInterval::instance($delay)->totalMinutes;

    return $minutes >= 1 && $minutes <= 15;
}

test('at 05:00 UTC the scheduler dispatches each Rating dataset sync, a delayed freshness check, the Discover build, the refresh planner and a delayed overdue check', function () {
    $this->travelTo('2026-11-06 05:00:00');
    Queue::fake();

    $this->artisan('schedule:run')->assertSuccessful();

    Queue::assertPushedOn('imports', SyncRatingDataset::class, fn (SyncRatingDataset $job) => $job->source === RatingSourceName::Imdb);
    Queue::assertPushed(CheckRatingDatasetFreshness::class, fn (CheckRatingDatasetFreshness $job) => delayedWithinLimit($job->delay));
    Queue::assertPushed(BuildDiscover::class);
    Queue::assertPushed(PlanMovieRefreshes::class, fn (PlanMovieRefreshes $job) => $job->afterId === 0);
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
