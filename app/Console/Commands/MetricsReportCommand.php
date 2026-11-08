<?php

namespace App\Console\Commands;

use App\Services\Analytics\Metrics;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Prints the beta's success metrics, run by hand: see `Metrics` for each definition. Counts are of signed-in users
 * only, as anonymous visitors aren't measured.
 */
#[Signature('metrics:report {--days=28 : Days in the window of the conversion and the daily table}')]
#[Description('Report the beta\'s success metrics from the analytics events')]
class MetricsReportCommand extends Command
{
    public function handle(Metrics $metrics): int
    {
        $days = (int) $this->option('days');

        if ($days < 1) {
            $this->components->error('--days must be at least 1.');

            return self::FAILURE;
        }

        $now = now()->toImmutable();
        $returning = $metrics->weeklyReturning($now);
        $conversion = $metrics->searchToWatchlist($now, $days);
        $picker = $metrics->pickerUsage($now);

        $this->components->twoColumnDetail('Weekly returning users (target 30%)', $this->describe($returning['rate'], "{$returning['returning']} of {$returning['activated']} activated"));
        $this->components->twoColumnDetail("Search-to-watchlist conversion, {$days} days (target 20%)", $this->describe($conversion['rate'], "{$conversion['converted']} of {$conversion['viewers']} viewers"));
        $this->components->twoColumnDetail('Movie picker usage, 7 days (target 15%)', $this->describe($picker['rate'], "{$picker['pickers']} of {$picker['active']} active"));

        $this->newLine();
        $this->table(
            ['Day', 'Searching users', 'Picker uses', 'Average pool size'],
            array_map(fn (array $day): array => [
                $day['day'],
                $day['searching_users'] ?? 'rolled up',
                $day['picker_uses'],
                $day['average_pool_size'] ?? '-',
            ], $metrics->daily($now, $days)),
        );

        return self::SUCCESS;
    }

    private function describe(?float $rate, string $counts): string
    {
        return $rate === null ? 'no data' : sprintf('%.1f%% (%s)', $rate * 100, $counts);
    }
}
