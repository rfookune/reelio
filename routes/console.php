<?php

use App\Jobs\BuildDiscover;
use App\Jobs\CheckOverdueRefreshes;
use App\Jobs\CheckRatingDatasetFreshness;
use App\Jobs\PlanMovieRefreshes;
use App\Jobs\RefreshGenres;
use App\Jobs\SyncRatingDataset;
use App\Services\Ratings\Sync\Datasets\RatingDatasets;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * The one daily slot. Every scheduled task runs from here and only dispatches jobs, so the app wakes once a day;
 * weekly tasks run in it on Mondays. The freshness and overdue checks are delayed so that they read what's stored
 * after the day's syncs and refreshes have had time to finish, within the 15-minute limit on a job's delay.
 */
Schedule::call(function (RatingDatasets $datasets): void {
    foreach ($datasets->enabled() as $dataset) {
        SyncRatingDataset::dispatch($dataset->source());
    }

    CheckRatingDatasetFreshness::dispatch()->delay(now()->addMinutes(10));
    BuildDiscover::dispatch();
    PlanMovieRefreshes::dispatch();
    CheckOverdueRefreshes::dispatch()->delay(now()->addMinutes(15));

    if (now('UTC')->isMonday()) {
        RefreshGenres::dispatch();
    }
})->name('daily-slot')->dailyAt('05:00')->timezone('UTC')->onOneServer();
