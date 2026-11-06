<?php

namespace App\Jobs;

use App\Services\Catalog\Catalog;
use App\Services\Catalog\Enums\CatalogProfile;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Rebuilds the Discover payload from the Catalog source with every Movie's details, daily at 05:00 UTC and after
 * a visitor had to build it. Visitors only read it. A failed section keeps its previous version, so the job isn't
 * retried: the next run is the retry.
 */
class BuildDiscover implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /** Below the 90 s at which Flex kills a job, so a slow build stops cleanly and the stored payload keeps serving. */
    public int $timeout = 85;

    public int $tries = 1;

    /** Held longer than a build can take, so a second dispatch can't start while one is running. */
    public int $uniqueFor = 120;

    public function handle(Catalog $catalog): void
    {
        $catalog->buildDiscover(CatalogProfile::Background);
    }
}
