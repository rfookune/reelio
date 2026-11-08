<?php

namespace App\Services\Analytics;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use stdClass;

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
     * activated by their first Movie or TV Show Watchlist event before that window starts.
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
                select distinct user_id from events where name in ('movie_watchlisted', 'tv_show_watchlisted') and occurred_at < ?
            ) as activated
            SQL, [$weekStart, $weekStart]);

        return ['activated' => (int) $row->activated, 'returning' => (int) $row->returning, 'rate' => $this->rate((int) $row->returning, (int) $row->activated)];
    }

    /**
     * Search-to-watchlist conversion: of the users who opened a Movie or TV Show in the window, the share who
     * also put a Movie or TV Show on their Watchlist in it.
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
                    where saved.user_id = viewed.user_id and saved.name in ('movie_watchlisted', 'tv_show_watchlisted') and saved.occurred_at >= ?
                )) as converted
            from events as viewed
            where viewed.name in ('movie_viewed', 'tv_show_viewed') and viewed.occurred_at >= ?
            SQL, [$start, $start]);

        return ['viewers' => (int) $row->viewers, 'converted' => (int) $row->converted, 'rate' => $this->rate((int) $row->converted, (int) $row->viewers)];
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

        return ['active' => (int) $row->active, 'pickers' => (int) $row->pickers, 'rate' => $this->rate((int) $row->pickers, (int) $row->active)];
    }

    /**
     * One row per day in the window, newest first: the distinct users searching (null for a day already rolled up,
     * which no longer knows its users; a day split by the roll-up cutoff counts only its users not yet rolled up), the
     * picker uses and the average pool size of the picker's Watchlist.
     *
     * @return list<array{day: string, searching_users: ?int, picker_uses: int, average_pool_size: ?float}>
     */
    public function daily(CarbonImmutable $now, int $days): array
    {
        $start = $this->windowStart($now, $days);

        $rows = DB::select(<<<'SQL'
            select day::text as day, sum(searching_users) as searching_users, sum(picker_uses) as picker_uses,
                sum(pool_size_total) as pool_size_total
            from (
                select occurred_at::date as day,
                    count(distinct user_id) filter (where name = 'title_searched') as searching_users,
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
            SQL, [$start, $start]);

        return array_values(array_map(fn (stdClass $row): array => [
            'day' => $row->day,
            'searching_users' => $row->searching_users === null ? null : (int) $row->searching_users,
            'picker_uses' => (int) $row->picker_uses,
            'average_pool_size' => $row->pool_size_total === null || (int) $row->picker_uses === 0
                ? null
                : round((int) $row->pool_size_total / (int) $row->picker_uses, 1),
        ], $rows));
    }

    /**
     * TV Shows in the window: Watchlist additions, users marking at least one Episode, Episode marks per
     * marking user (including bulk marks), and completions. Anonymous roll-ups preserve action counts only.
     *
     * @return array{watchlisted: int, marking_users: int, marks_per_user: ?float, completed: int}
     */
    public function tvShows(CarbonImmutable $now, int $days): array
    {
        $start = $this->windowStart($now, $days);
        $row = DB::selectOne(<<<'SQL'
            with recent as (
                select user_id, name,
                    case when name = 'episode_marked_watched' then 1
                        when name in ('season_marked_watched', 'tv_show_marked_watched')
                            and properties->>'episode_count' ~ '^[0-9]{1,15}$'
                        then (properties->>'episode_count')::bigint else 0 end as episode_marks
                from events where occurred_at >= ?
            ), counts as (
                select count(*) filter (where name = 'tv_show_watchlisted') as watchlisted,
                    count(*) filter (where name = 'tv_show_completed') as completed
                from recent
                union all
                select coalesce(sum(count) filter (where name = 'tv_show_watchlisted'), 0),
                    coalesce(sum(count) filter (where name = 'tv_show_completed'), 0)
                from event_daily_counts where day >= ?::date
            )
            select (select sum(watchlisted) from counts) as watchlisted,
                (select sum(completed) from counts) as completed,
                count(distinct user_id) filter (where episode_marks > 0) as marking_users,
                coalesce(sum(episode_marks), 0) as episode_marks
            from recent
            SQL, [$start, $start]);

        return [
            'watchlisted' => (int) $row->watchlisted,
            'marking_users' => (int) $row->marking_users,
            'marks_per_user' => (int) $row->marking_users === 0 ? null : round((int) $row->episode_marks / (int) $row->marking_users, 1),
            'completed' => (int) $row->completed,
        ];
    }

    private function windowStart(CarbonImmutable $now, int $days): string
    {
        return $now->subDays($days)->toDateTimeString();
    }

    private function rate(int $part, int $whole): ?float
    {
        return $whole === 0 ? null : round($part / $whole, 3);
    }
}
