<?php

namespace App\Jobs;

use App\Ratings\Imdb\ImdbRatingsImporter;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Alerts the owner when the live IMDb ratings table is more than three days old, or was never imported.
 * Ratings stay on show regardless. The table counts as current as of the later of its `imported_at` and the
 * importer's last success, because an unchanged file (304) doesn't rewrite `imported_at`. The cache hands that
 * timestamp back as a numeric string.
 */
class CheckImdbRatingsFreshness implements ShouldQueue
{
    use Queueable;

    private const MAXIMUM_AGE_DAYS = 3;

    public function handle(): void
    {
        $importedAt = DB::table('imdb_ratings')->max('imported_at');

        if ($importedAt === null) {
            Log::error('The IMDb ratings table has never been imported.');

            return;
        }

        $lastSuccess = Cache::get(ImdbRatingsImporter::LAST_SUCCESS_CACHE_KEY);
        $current = CarbonImmutable::parse($importedAt);

        if (is_numeric($lastSuccess)) {
            $current = $current->max(CarbonImmutable::createFromTimestamp((int) $lastSuccess));
        }

        if ($current->lt(now()->subDays(self::MAXIMUM_AGE_DAYS))) {
            Log::error('The IMDb ratings table is more than three days old.', ['current_as_of' => $current->toDateTimeString()]);
        }
    }
}
