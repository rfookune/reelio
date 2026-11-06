<?php

use App\Jobs\CheckImdbRatingsFreshness;
use App\Jobs\ImportImdbRatings;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * The one daily slot. Every scheduled task runs from here and only dispatches jobs, so the app wakes once a day.
 * The IMDb freshness check is delayed so that it reads the table after the day's import has had time to finish.
 */
Schedule::call(function (): void {
    ImportImdbRatings::dispatch();
    CheckImdbRatingsFreshness::dispatch()->delay(now()->addMinutes(10));
})->name('daily-slot')->dailyAt('05:00')->timezone('UTC')->onOneServer();
