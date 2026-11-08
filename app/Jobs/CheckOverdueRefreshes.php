<?php

namespace App\Jobs;

use App\Services\Catalog\Maintenance\RefreshSchedule;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Alerts the owner, daily, when Movies users hold are more than `catalog.refresh.overdue_alert_after_days` past
 * their scheduled refresh: their refreshes keep failing, and what's stored is ageing toward TMDB's cap. It runs
 * after the day's planned refreshes, so a Movie refreshed today isn't counted. One alert names how many, and those
 * synced longest ago.
 */
class CheckOverdueRefreshes implements ShouldQueue
{
    use Queueable;

    private const SAMPLE_SIZE = 10;

    public function handle(RefreshSchedule $schedule): void
    {
        $overdue = $schedule->overdueForRefresh(self::SAMPLE_SIZE);

        if ($overdue['count'] > 0) {
            Log::error('Movies users hold are overdue for refresh.', [
                'count' => $overdue['count'],
                'movie_ids' => $overdue['sample'],
                'overdue_after_days' => config('catalog.refresh.overdue_alert_after_days'),
            ]);
        }
    }
}
