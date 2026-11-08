<?php

namespace App\Services\Catalog\Maintenance;

use App\Models\Movie;
use Closure;
use Illuminate\Database\Eloquent\Builder;

class RefreshSchedule
{
    /**
     * The ids of up to `$limit` Movies due a scheduled refresh, in id order after `$afterId`, so the planner can
     * page through them while refreshes drop Movies out of the set.
     *
     * @return list<int>
     */
    public function dueForRefresh(int $afterId, int $limit): array
    {
        return $this->movieIds($this->heldMoviesPastSchedule(0)->where('id', '>', $afterId)->orderBy('id')->limit($limit));
    }

    /**
     * How many Movies someone holds are more than `refresh.overdue_alert_after_days` past their scheduled refresh,
     * and the ids of those synced longest ago, up to `$sampleSize`.
     *
     * @return array{count: int, sample: list<int>}
     */
    public function overdueForRefresh(int $sampleSize): array
    {
        $overdue = fn (): Builder => $this->heldMoviesPastSchedule(config('catalog.refresh.overdue_alert_after_days'));

        return [
            'count' => $overdue()->count(),
            'sample' => $this->movieIds($overdue()->orderByRaw('synced_at asc nulls first')->orderBy('id')->limit($sampleSize)),
        ];
    }

    /**
     * Movies someone holds whose last sync is older than their schedule allows, plus `$graceDays`: on any Watchlist,
     * `refresh.watchlist_after_days`; only Watched, `refresh.watched_after_days`. A Movie with no sync at all, as
     * one whose details were cleared, is always due. A Removed Movie never is: it isn't fetched again.
     *
     * @return Builder<Movie>
     */
    private function heldMoviesPastSchedule(int $graceDays): Builder
    {
        $syncedOver = fn (int $days): Closure => fn (Builder $query): Builder => $query
            ->whereNull('synced_at')
            ->orWhere('synced_at', '<', now()->subDays($days + $graceDays));
        $onAWatchlist = fn (Builder $facts): Builder => $facts->whereNotNull('watchlisted_at');

        return Movie::query()
            ->whereNull('removed_at')
            ->where(fn (Builder $query): Builder => $query
                ->where(fn (Builder $watchlisted): Builder => $watchlisted
                    ->whereHas('userMovies', $onAWatchlist)
                    ->where($syncedOver(config('catalog.refresh.watchlist_after_days'))))
                ->orWhere(fn (Builder $watchedOnly): Builder => $watchedOnly
                    ->whereHas('userMovies')
                    ->whereDoesntHave('userMovies', $onAWatchlist)
                    ->where($syncedOver(config('catalog.refresh.watched_after_days')))));
    }

    /**
     * @param  Builder<Movie>  $query
     * @return list<int>
     */
    private function movieIds(Builder $query): array
    {
        return array_values($query->get(['id'])->map(fn (Movie $movie): int => $movie->id)->all());
    }
}
