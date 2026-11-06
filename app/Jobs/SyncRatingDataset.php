<?php

namespace App\Jobs;

use App\Ratings\Enums\RatingSourceName;
use App\Ratings\Sync\RatingDatasets;
use App\Ratings\Sync\RatingDatasetSync;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The daily sync of one full-dataset Rating source. A failure leaves the source's live rows serving; the next
 * daily run retries. It is unique per source, so two runs never replace the same source at once.
 */
class SyncRatingDataset implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /**
     * Generous, and below the queue's `retry_after` so a slow run isn't delivered twice: the local `database`
     * connection's here, and the Cloud `imports` queue's, which is set on Cloud.
     */
    public int $timeout = 300;

    public int $tries = 1;

    /**
     * Held longer than a run can take, so a second dispatch can't start while one is running. A redelivered copy
     * is stopped by `$tries = 1` instead.
     */
    public int $uniqueFor = 600;

    public function __construct(public RatingSourceName $source)
    {
        $this->onQueue('imports');
    }

    public function uniqueId(): string
    {
        return $this->source->value;
    }

    public function handle(RatingDatasets $datasets, RatingDatasetSync $sync): void
    {
        $dataset = $datasets->for($this->source);

        if ($dataset !== null) {
            $sync->sync($dataset);
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Rating dataset sync failed; the live rows are unchanged.', ['source' => $this->source->value, 'reason' => $exception->getMessage()]);
    }
}
