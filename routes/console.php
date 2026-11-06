<?php

use App\Jobs\BuildDiscover;
use App\Jobs\CheckRatingDatasetFreshness;
use App\Jobs\SyncRatingDataset;
use App\Services\Ratings\Sync\Datasets\RatingDatasets;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * The one daily slot. Every scheduled task runs from here and only dispatches jobs, so the app wakes once a day.
 * The freshness check is delayed so that it reads each source after the day's sync has had time to finish.
 */
Schedule::call(function (RatingDatasets $datasets): void {
    foreach ($datasets->enabled() as $dataset) {
        SyncRatingDataset::dispatch($dataset->source());
    }

    CheckRatingDatasetFreshness::dispatch()->delay(now()->addMinutes(10));
    BuildDiscover::dispatch();
})->name('daily-slot')->dailyAt('05:00')->timezone('UTC')->onOneServer();
