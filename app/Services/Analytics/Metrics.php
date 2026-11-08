<?php

namespace App\Services\Analytics;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * The beta's success metrics, each defined once here as documented SQL over `events` and `event_daily_counts`. The
 * rolled-up table has no users, so a metric that counts distinct users reads `events` alone, which holds the last 13
 * months; a metric that only counts uses reads both. A window of days ends at `$now` and is 7 days for the weekly metrics.
 */
class Metrics
{
    /** Days in the window of the weekly metrics. */
    public const WEEK_DAYS = 7;

    /**
     * Weekly returning users: of the activated users, the share with any event in the last 7 days. A user is
     * activated by their first `movie_watchlisted` event before that window starts.
     *
     * @return array{activated: int, returning: int, rate: ?float}
     */
    public function weeklyReturning(CarbonImmutable $now): array
    {
        $weekStart = $this->windowStart($now, self::WEEK_DAYS);

        $row = DB::selectOne(<<<'SQL'
            select count(*) as activated,
                count(*) filter (where exists (
                    select 1 from events as recent where recent.user_id = activated.user_id and recent.occurred_at >= ?
                )) as returning
            from (
                select distinct user_id from events where name = 'movie_watchlisted' and occurred_at < ?
            ) as activated
            SQL, [$weekStart, $weekStart]);

        return $this->share((int) $row->returning, (int) $row->activated, 'returning', 'activated');
    }

    /**
     * Search-to-watchlist conversion: of the users who opened a Movie (`movie_viewed`) in the window, the share who
     * also put one on their Watchlist (`movie_watchlisted`) in it.
     *
     * @return array{viewers: int, converted: int, rate: ?float}
     */
    public function searchToWatchlist(CarbonImmutable $now, int $days): array
    {
        $start = $this->windowStart($now, $days);

        $row = DB::selectOne(<<<'SQL'
            select count(distinct viewed.user_id) as viewers,
                count(distinct viewed.user_id) filter (where exists (
                    select 1 from events as saved
                    where saved.user_id = viewed.user_id and saved.name = 'movie_watchlisted' and saved.occurred_at >= ?
                )) as converted
            from events as viewed
            where viewed.name = 'movie_viewed' and viewed.occurred_at >= ?
            SQL, [$start, $start]);

        return $this->share((int) $row->converted, (int) $row->viewers, 'converted', 'viewers');
    }

    /**
     * Movie picker usage: of the weekly active users (any event in the last 7 days), the share who used the picker.
     *
     * @return array{active: int, pickers: int, rate: ?float}
     */
    public function pickerUsage(CarbonImmutable $now): array
    {
        $row = DB::selectOne(<<<'SQL'
            select count(distinct user_id) as active,
                count(distinct user_id) filter (where name = 'movie_picker_used') as pickers
            from events
            where occurred_at >= ?
            SQL, [$this->windowStart($now, self::WEEK_DAYS)]);

        return $this->share((int) $row->pickers, (int) $row->active, 'pickers', 'active');
    }

    /**
     * One row per day in the window, newest first: the distinct users searching (null for a day already rolled up,
     * which no longer knows its users; a day split by the roll-up cutoff counts only its users not yet rolled up), the picker uses and the average pool size of the picker's Watchlist.
     *
     * @return list<array{day: string, searching_users: ?int, picker_uses: int, average_pool_size: ?float}>
     */
    public function daily(CarbonImmutable $now, int $days): array
    {
        $start = $this->windowStart($now, $days);

        return array_map(fn (object $row): array => [
            'day' => $row->day,
            'searching_users' => $row->searching_users === null ? null : (int) $row->searching_users,
            'picker_uses' => (int) $row->picker_uses,
            'average_pool_size' => $row->pool_size_total === null || (int) $row->picker_uses === 0
                ? null
                : round((int) $row->pool_size_total / (int) $row->picker_uses, 1),
        ], DB::select(<<<'SQL'
            select day::text as day, sum(searching_users) as searching_users, sum(picker_uses) as picker_uses,
                sum(pool_size_total) as pool_size_total
            from (
                select occurred_at::date as day,
                    count(distinct user_id) filter (where name = 'movie_searched') as searching_users,
                    count(*) filter (where name = 'movie_picker_used') as picker_uses,
                    sum(case when properties->>'pool_size' ~ '^[0-9]{1,15}$' then (properties->>'pool_size')::bigint end) filter (where name = 'movie_picker_used') as pool_size_total
                from events
                where occurred_at >= ?
                group by occurred_at::date
                union all
                select day, null, coalesce(sum(count) filter (where name = 'movie_picker_used'), 0), sum(pool_size_total)
                from event_daily_counts
                where day >= ?::date
                group by day
            ) as days
            group by day
            order by day desc
            SQL, [$start, $start]));
    }

    private function windowStart(CarbonImmutable $now, int $days): string
    {
        return $now->subDays($days)->toDateTimeString();
    }

    /**
     * @return array<string, int|float|null>
     */
    private function share(int $part, int $whole, string $partKey, string $wholeKey): array
    {
        return [$wholeKey => $whole, $partKey => $part, 'rate' => $whole === 0 ? null : round($part / $whole, 3)];
    }
}
