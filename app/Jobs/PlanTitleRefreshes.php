<?php

namespace App\Jobs;

use App\Services\Catalog\Maintenance\RefreshSchedule;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Keeps the Movies and TV Shows users hold fresh without anyone viewing them, daily at 05:00 UTC. Each run dispatches one
 * refresh per due Title for a page of each kind, then hands the rest to a next run, so no run nears the 90 s at which
 * Flex kills a job. Each Title's refresh is unique, so a repeated run dispatches nothing twice.
 */
class PlanTitleRefreshes implements ShouldQueue
{
    use Queueable;

    public int $timeout = 85;

    /** The next daily run is the retry. */
    public int $tries = 1;

    /**
     * @param  int  $afterId  the last Movie id an earlier run dispatched
     * @param  int  $afterTvShowId  the last TV Show id an earlier run dispatched
     */
    public function __construct(public int $afterId = 0, public int $afterTvShowId = 0)
    {
        $this->onQueue('default');
    }

    public function handle(RefreshSchedule $schedule): void
    {
        $pageSize = config('catalog.refresh.planner_page_size');
        $movieIds = $schedule->dueForRefresh($this->afterId, $pageSize);

        $lastMovieId = $this->afterId;

        foreach ($movieIds as $movieId) {
            RefreshMovie::dispatch($movieId);
            $lastMovieId = $movieId;
        }

        $tvShowIds = $schedule->dueTvShowsForRefresh($this->afterTvShowId, $pageSize);
        $lastTvShowId = $this->afterTvShowId;
        foreach ($tvShowIds as $tvShowId) {
            RefreshTvShow::dispatch($tvShowId);
            $lastTvShowId = $tvShowId;
        }

        if (count($movieIds) === $pageSize || count($tvShowIds) === $pageSize) {
            self::dispatch($lastMovieId, $lastTvShowId);
        }
    }
}
