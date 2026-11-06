<?php

use App\Jobs\CheckRatingDatasetFreshness;
use App\Jobs\SyncRatingDataset;
use App\Services\Ratings\Enums\RatingSourceName;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Queue;

test('the single daily slot runs at 05:00 UTC and dispatches each Rating dataset sync and a delayed freshness check', function () {
    Queue::fake();
    $events = collect(app(Schedule::class)->events());

    expect($events)->toHaveCount(1);
    $slot = $events->sole();
    expect($slot->expression)->toBe('0 5 * * *')
        ->and($slot->timezone)->toBe('UTC');

    $slot->run(app());

    Queue::assertPushedOn('imports', SyncRatingDataset::class, fn (SyncRatingDataset $job) => $job->source === RatingSourceName::Imdb);
    Queue::assertPushed(CheckRatingDatasetFreshness::class, fn (CheckRatingDatasetFreshness $job) => $job->delay !== null);
});

test('a switched-off source is not dispatched', function () {
    Queue::fake();
    config(['ratings.sources.imdb.enabled' => false]);

    collect(app(Schedule::class)->events())->sole()->run(app());

    Queue::assertNotPushed(SyncRatingDataset::class);
});
