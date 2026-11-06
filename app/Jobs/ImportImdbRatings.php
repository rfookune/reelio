<?php

namespace App\Jobs;

use App\Ratings\Imdb\ImdbRatingsImporter;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The daily IMDb ratings import. A failure leaves the live table serving; the next daily run retries.
 * It is unique, so two runs never share the staging table.
 */
class ImportImdbRatings implements ShouldBeUnique, ShouldQueue
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

    public function __construct()
    {
        $this->onQueue('imports');
    }

    public function handle(ImdbRatingsImporter $importer): void
    {
        $importer->import();
    }

    public function failed(Throwable $exception): void
    {
        Log::error('IMDb ratings import failed.', ['reason' => $exception->getMessage()]);
    }
}
