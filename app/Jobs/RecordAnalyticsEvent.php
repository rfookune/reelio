<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Throwable;

class RecordAnalyticsEvent implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, bool|int|string|null>  $properties
     */
    public function __construct(
        public string $uuid,
        public int $userId,
        public string $name,
        public array $properties,
        public string $occurredAt,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            DB::table('events')->insertOrIgnore([
                'uuid' => $this->uuid,
                'user_id' => $this->userId,
                'name' => $this->name,
                'properties' => json_encode((object) $this->properties, JSON_THROW_ON_ERROR),
                'occurred_at' => $this->occurredAt,
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
