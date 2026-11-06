<?php

namespace App\Listeners;

use App\Http\VisitorCountry;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\Request;

/**
 * On sign-in or registration (which signs the new user in), a guest's explicit country choice becomes the
 * account's `preferred_country`, but only when the account has none. A detected country is never copied.
 */
class CopyGuestCountryToAccount
{
    public function __construct(private Request $request) {}

    public function handle(Login $event): void
    {
        if (! $event->user instanceof User || $event->user->preferred_country !== null) {
            return;
        }

        $guestChoice = VisitorCountry::guestChoice($this->request);

        if ($guestChoice !== null) {
            $event->user->update(['preferred_country' => $guestChoice]);
        }
    }
}
