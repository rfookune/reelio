<?php

namespace App\Http\Controllers;

use App\Http\Requests\CountryUpdateRequest;
use App\Http\Support\VisitorCountry;
use Illuminate\Http\RedirectResponse;

/**
 * The "Showing {country}" selector: an explicit choice is saved to a signed-in user's account, or remembered in
 * a guest's country cookie.
 */
class CountryController extends Controller
{
    public function __invoke(CountryUpdateRequest $request): RedirectResponse
    {
        $country = $request->validated('country');
        $user = $request->user();

        if ($user !== null) {
            $user->update(['preferred_country' => $country]);

            return back();
        }

        return back()->withCookie(cookie(VisitorCountry::COOKIE, $country, VisitorCountry::COOKIE_MINUTES));
    }
}
