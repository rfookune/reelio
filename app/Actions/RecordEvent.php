<?php

namespace App\Actions;

use App\Jobs\RecordAnalyticsEvent;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use Throwable;

class RecordEvent
{
    /**
     * @param  array<string, bool|int|string|null>  $properties
     */
    public function handle(?User $user, string $name, array $properties = []): void
    {
        if ($user === null) {
            return;
        }

        $job = new RecordAnalyticsEvent(
            uuid: (string) Str::uuid(),
            userId: $user->id,
            name: $name,
            properties: $properties,
            occurredAt: now()->toDateTimeString(),
        );
        $job->onQueue('default');

        app()->terminating(function () use ($job): void {
            try {
                Bus::dispatch($job);
            } catch (Throwable $exception) {
                report($exception);
            }
        });
    }
}
