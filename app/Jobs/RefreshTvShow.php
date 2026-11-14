<?php

namespace App\Jobs;

use App\Models\TvShow;
use App\Services\Catalog\Catalog;
use App\Services\Catalog\CatalogContext;
use App\Services\Catalog\Enums\CatalogProfile;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * One scheduled refresh of a stored TV Show: a single background-profile call to the Catalog source. A failure
 * leaves what's stored serving, and the TV Show stays due, so the next daily plan is the retry. A TV Show deleted
 * since it was planned is skipped.
 */
class RefreshTvShow implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /** Room for one background call with its retries and throttle waits, including all Seasons. */
    public int $timeout = 85;

    public int $tries = 1;

    /** A lock left by a job that never finished expires well before the next daily plan. */
    public int $uniqueFor = 43200;

    public function __construct(public int $tvShowId)
    {
        $this->onQueue('default');
    }

    public function uniqueId(): string
    {
        return (string) $this->tvShowId;
    }

    public function handle(Catalog $catalog): void
    {
        $tvShow = TvShow::query()->find($this->tvShowId);

        if ($tvShow !== null) {
            $catalog->refreshTvShow($tvShow, new CatalogContext(config('catalog.language'), profile: CatalogProfile::Background));
        }
    }
}
