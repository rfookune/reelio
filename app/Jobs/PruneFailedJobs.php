<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;

/**
 * Keeps Reelio's published 7-day retention for failed jobs, daily at 05:00 UTC, by running Laravel's
 * `queue:prune-failed --hours=168`. Mail payloads in a failed job can hold email addresses. Repeating the job does no
 * harm.
 */
class PruneFailedJobs implements ShouldQueue
{
    use Queueable;

    /** Hours a failed job is kept. */
    public const RETENTION_HOURS = 168;

    /** The next daily run is the retry. */
    public int $tries = 1;

    public function __construct()
    {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        Artisan::call('queue:prune-failed', ['--hours' => self::RETENTION_HOURS]);
    }
}
