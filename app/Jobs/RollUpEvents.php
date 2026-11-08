<?php

namespace App\Jobs;

use App\Enums\AnalyticsEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

/**
 * Keeps Reelio's published 13-month retention for analytics events, daily at 05:00 UTC. Events older than 13 months
 * become one anonymous count per day and event name in `event_daily_counts` (`movie_picker_used` also keeps the total
 * of its `pool_size`) and are deleted. Deleting and counting are one statement, so no event is counted twice or lost, and a later run adds to a day an earlier run already rolled up.
 */
class RollUpEvents implements ShouldQueue
{
    use Queueable;

    /** Months an event is kept before it is reduced to a count. */
    public const RETENTION_MONTHS = 13;

    /** The next daily run is the retry. */
    public int $tries = 1;

    public function __construct()
    {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        $cutoff = now()->subMonths(self::RETENTION_MONTHS)->toDateTimeString();

        DB::statement(<<<'SQL'
            with moved as (
                delete from events where occurred_at < ? returning name, properties, occurred_at
            )
            insert into event_daily_counts (day, name, count, pool_size_total)
            select occurred_at::date, name, count(*),
                case when name = ? then coalesce(sum(
                    case when properties->>'pool_size' ~ '^[0-9]{1,15}$' then (properties->>'pool_size')::bigint end
                ), 0) end
            from moved
            group by occurred_at::date, name
            on conflict (day, name) do update set
                count = event_daily_counts.count + excluded.count,
                pool_size_total = case
                    when excluded.pool_size_total is null then event_daily_counts.pool_size_total
                    else coalesce(event_daily_counts.pool_size_total, 0) + excluded.pool_size_total
                end
            SQL, [
            $cutoff,
            AnalyticsEvent::MoviePickerUsed->value,
        ]);
    }
}
