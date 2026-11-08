<?php

namespace App\Listeners;

use App\Actions\RecordEvent;
use App\Enums\AnalyticsEvent;
use App\Models\User;
use Illuminate\Auth\Events\Registered;

class RecordAccountRegistration
{
    public function __construct(private RecordEvent $recordEvent) {}

    /**
     * Handle the event.
     */
    public function handle(Registered $event): void
    {
        if ($event->user instanceof User) {
            $this->recordEvent->handle($event->user, AnalyticsEvent::AccountRegistered);
        }
    }
}
