<?php

namespace App\Jobs;

use App\Ratings\RatingStore;
use App\Ratings\Sync\Datasets\RatingDatasets;
use App\Ratings\Sync\RatingDatasetSync;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Alerts the owner when a full-dataset Rating source was never synced or is more than three days old. Its
 * ratings stay on show regardless. A source counts as current as of the later of its newest row and its last
 * successful sync, because an unchanged file (304) doesn't rewrite any rows.
 */
class CheckRatingDatasetFreshness implements ShouldQueue
{
    use Queueable;

    private const MAXIMUM_AGE_DAYS = 3;

    public function handle(RatingDatasets $datasets, RatingDatasetSync $sync, RatingStore $store): void
    {
        foreach ($datasets->enabled() as $dataset) {
            $source = $dataset->source();
            $latestAsOf = $store->latestAsOf($source);

            if ($latestAsOf === null) {
                Log::error('A rating dataset has never been synced.', ['source' => $source->value]);

                continue;
            }

            $lastSuccess = $sync->lastSuccessAt($source);
            $currentAsOf = $lastSuccess === null ? $latestAsOf : $latestAsOf->max($lastSuccess);

            if ($currentAsOf->lt(now()->subDays(self::MAXIMUM_AGE_DAYS))) {
                Log::error('A rating dataset is out of date.', ['source' => $source->value, 'current_as_of' => $currentAsOf->toDateTimeString(), 'maximum_age_days' => self::MAXIMUM_AGE_DAYS]);
            }
        }
    }
}
