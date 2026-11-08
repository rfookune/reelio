<?php

namespace App\Actions;

use App\Enums\AnalyticsEvent;
use App\Jobs\RecordAnalyticsEvent;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use Throwable;

class RecordEvent
{
    /**
     * Queues the event once the response is sent. The app keeps terminating callbacks after it terminates, so the
     * callback queues it at most once, however many requests it lives through, as in a test or a long-lived worker.
     *
     * @param  array<string, bool|int|string|null>  $properties
     */
    public function handle(?User $user, AnalyticsEvent $name, array $properties = []): void
    {
        if ($user === null) {
            return;
        }

        $job = new RecordAnalyticsEvent(
            uuid: (string) Str::uuid(),
            userId: $user->id,
            name: $name->value,
            properties: $properties,
            occurredAt: now()->toDateTimeString(),
        );
        $job->onQueue('default');

        $queued = false;

        app()->terminating(function () use ($job, &$queued): void {
            if ($queued) {
                return;
            }

            $queued = true;

            try {
                Bus::dispatch($job);
            } catch (Throwable $exception) {
                report($exception);
            }
        });
    }
}
