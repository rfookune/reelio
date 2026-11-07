<?php

namespace App\Console\Commands;

use App\Jobs\SyncRatingDataset;
use App\Services\Ratings\Enums\RatingSourceName;
use App\Services\Ratings\Sync\Datasets\RatingDatasets;
use App\Services\Ratings\Sync\Enums\RatingSyncOutcome;
use App\Services\Ratings\Sync\RatingDatasetSync;
use App\Services\Ratings\Sync\RatingSyncFailed;
use Illuminate\Bus\UniqueLock;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Runs a full-dataset Rating source's sync now, in the foreground, instead of waiting for the daily slot. It holds
 * the sync job's per-source lock while it runs, so it never replaces a source at the same time as a queued run.
 */
#[Signature('ratings:sync {source=imdb : The Rating source to sync}')]
#[Description('Sync a full-dataset Rating source now')]
class SyncRatingDatasetCommand extends Command
{
    public function handle(RatingDatasets $datasets, RatingDatasetSync $sync): int
    {
        $source = RatingSourceName::tryFrom((string) $this->argument('source'));

        if ($source === null) {
            $this->components->error('Unknown Rating source. Valid sources: '.implode(', ', array_column(RatingSourceName::cases(), 'value')).'.');

            return self::FAILURE;
        }

        if (! $source->isEnabled()) {
            $this->components->error("{$source->label()} is switched off, so it isn't synced.");

            return self::FAILURE;
        }

        $dataset = $datasets->for($source);

        if ($dataset === null) {
            $this->components->error("{$source->label()} has no dataset to sync.");

            return self::FAILURE;
        }

        $job = new SyncRatingDataset($source);
        $lock = new UniqueLock(Cache::store());

        if (! $lock->acquire($job)) {
            $this->components->error("A sync for {$source->label()} is already queued or running.");

            return self::FAILURE;
        }

        try {
            $result = $sync->sync($dataset);
        } catch (RatingSyncFailed $exception) {
            $this->components->error("The {$source->label()} sync failed; the live rows are unchanged. {$exception->getMessage()}");

            return self::FAILURE;
        } finally {
            $lock->release($job);
        }

        if ($result->outcome === RatingSyncOutcome::Unchanged) {
            $this->components->info("The {$source->label()} dataset hasn't changed since the last sync; nothing was replaced.");
        } else {
            $this->components->info("Synced {$source->label()}: {$result->imported} imported, {$result->skipped} skipped.");
        }

        return self::SUCCESS;
    }
}
