<?php

namespace App\Jobs;

use App\Services\Catalog\Catalog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Keeps the Movies users hold fresh without anyone viewing them, daily at 05:00 UTC. Each run dispatches one
 * refresh per due Movie for a page of them, then hands the rest to a next run, so no run nears the 90 s at which
 * Flex kills a job. Each Movie's refresh is unique, so a repeated run dispatches nothing twice.
 */
class PlanMovieRefreshes implements ShouldQueue
{
    use Queueable;

    public int $timeout = 85;

    /** The next daily run is the retry. */
    public int $tries = 1;

    /** @param  int  $afterId  the last Movie id an earlier run of the day dispatched */
    public function __construct(public int $afterId = 0)
    {
        $this->onQueue('default');
    }

    public function handle(Catalog $catalog): void
    {
        $pageSize = config('catalog.refresh.planner_page_size');
        $movieIds = $catalog->dueForRefresh($this->afterId, $pageSize);

        $lastMovieId = $this->afterId;

        foreach ($movieIds as $movieId) {
            RefreshMovie::dispatch($movieId);
            $lastMovieId = $movieId;
        }

        if (count($movieIds) === $pageSize) {
            self::dispatch($lastMovieId);
        }
    }
}
