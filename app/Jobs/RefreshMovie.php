<?php

namespace App\Jobs;

use App\Models\Movie;
use App\Services\Catalog\Catalog;
use App\Services\Catalog\CatalogContext;
use App\Services\Catalog\Enums\CatalogProfile;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * One scheduled refresh of a stored Movie: a single background-profile call to the Catalog source. A failure
 * leaves what's stored serving, and the Movie stays due, so the next daily plan is the retry. A Movie deleted
 * since it was planned is skipped.
 */
class RefreshMovie implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /** Room for one background call with its retries and throttle waits, about 40 s at worst. */
    public int $timeout = 85;

    public int $tries = 1;

    /** A lock left by a job that never finished expires well before the next daily plan. */
    public int $uniqueFor = 43200;

    public function __construct(public int $movieId)
    {
        $this->onQueue('default');
    }

    public function uniqueId(): string
    {
        return (string) $this->movieId;
    }

    public function handle(Catalog $catalog): void
    {
        $movie = Movie::query()->find($this->movieId);

        if ($movie !== null) {
            $catalog->refresh($movie, new CatalogContext(config('catalog.language'), profile: CatalogProfile::Background));
        }
    }
}
