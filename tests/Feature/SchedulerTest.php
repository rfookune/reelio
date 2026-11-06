<?php

use App\Jobs\CheckImdbRatingsFreshness;
use App\Jobs\ImportImdbRatings;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Queue;

test('the single daily slot runs at 05:00 UTC and dispatches the IMDb import and its freshness check', function () {
    Queue::fake();
    $events = collect(app(Schedule::class)->events());

    expect($events)->toHaveCount(1);
    $slot = $events->sole();
    expect($slot->expression)->toBe('0 5 * * *')
        ->and($slot->timezone)->toBe('UTC');

    $slot->run(app());

    Queue::assertPushedOn('imports', ImportImdbRatings::class);
    Queue::assertPushed(CheckImdbRatingsFreshness::class, fn (CheckImdbRatingsFreshness $job) => $job->delay !== null);
});
