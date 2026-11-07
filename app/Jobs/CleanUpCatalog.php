<?php

namespace App\Jobs;

use App\Services\Catalog\Catalog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Keeps Reelio within TMDB's limits, daily at 05:00 UTC: see `Catalog::cleanUp()`. Each run handles a batch per
 * step and hands what's left to a next run, so no run nears the 90 s at which Flex kills a job. Every step is safe
 * to repeat, so a redelivered run does no harm.
 */
class CleanUpCatalog implements ShouldQueue
{
    use Queueable;

    public int $timeout = 85;

    /** The next daily run is the retry. */
    public int $tries = 1;

    public function __construct()
    {
        $this->onQueue('default');
    }

    public function handle(Catalog $catalog): void
    {
        if ($catalog->cleanUp()) {
            self::dispatch();
        }
    }
}
