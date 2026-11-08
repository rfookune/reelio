<?php

use App\Jobs\BuildDiscover;
use App\Jobs\BuildSitemap;
use App\Jobs\CheckOverdueRefreshes;
use App\Jobs\CheckRatingDatasetFreshness;
use App\Jobs\CleanUpCatalog;
use App\Jobs\ClearExpiredPasswordResets;
use App\Jobs\PlanMovieRefreshes;
use App\Jobs\PruneFailedJobs;
use App\Jobs\PruneUnverifiedAccounts;
use App\Jobs\RefreshGenres;
use App\Jobs\RollUpEvents;
use App\Jobs\SyncRatingDataset;
use App\Services\Ratings\Sync\Datasets\RatingDatasets;
use Carbon\CarbonInterval;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * The one daily slot, 05:00 UTC. Every scheduled task runs in it and only dispatches a queued job, so the app wakes
 * once a day; weekly tasks run in it on Mondays. The freshness and overdue checks and the sitemap build are delayed so that they read
 * what's stored after the day's syncs and refreshes have had time to finish, within the 15-minute limit on a job's
 * delay. A switched-off Rating source isn't synced. Each task has its own name, which `onOneServer()` locks on.
 */
Schedule::dailyAt('05:00')->timezone('UTC')->onOneServer()->group(function (): void {
    foreach (app(RatingDatasets::class)->all() as $dataset) {
        $source = $dataset->source();
        Schedule::job(new SyncRatingDataset($source))->name(SyncRatingDataset::class.":{$source->value}")->when(fn (): bool => $source->isEnabled());
    }

    Schedule::job((new CheckRatingDatasetFreshness)->delay(CarbonInterval::minutes(10)));
    Schedule::job(new BuildDiscover);
    Schedule::job(new PlanMovieRefreshes);
    Schedule::job(new CleanUpCatalog);
    Schedule::job(new PruneUnverifiedAccounts);
    Schedule::job(new ClearExpiredPasswordResets);
    Schedule::job(new PruneFailedJobs);
    Schedule::job(new RollUpEvents);
    Schedule::job((new BuildSitemap)->delay(CarbonInterval::minutes(10)));
    Schedule::job((new CheckOverdueRefreshes)->delay(CarbonInterval::minutes(15)));
    Schedule::job(new RefreshGenres)->mondays();
});
