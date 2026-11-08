<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;

/**
 * Keeps Reelio's published 60-minute retention for password-reset tokens, daily at 05:00 UTC, by running Laravel's
 * `auth:clear-resets`. Repeating the job does no harm.
 */
class ClearExpiredPasswordResets implements ShouldQueue
{
    use Queueable;

    /** The next daily run is the retry. */
    public int $tries = 1;

    public function __construct()
    {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        Artisan::call('auth:clear-resets');
    }
}
