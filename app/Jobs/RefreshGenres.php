<?php

namespace App\Jobs;

use App\Services\Catalog\Enums\CatalogProfile;
use App\Services\Catalog\Maintenance\GenreRefresh;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Brings the stored genre list up to date with the Catalog source, weekly on Mondays at 05:00 UTC. A failure
 * leaves the stored genres as they are, and the next week's run is the retry.
 */
class RefreshGenres implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function handle(GenreRefresh $genres): void
    {
        $genres->refreshGenres(CatalogProfile::Background);
    }
}
