<?php

namespace App\Actions\Fortify;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Laravel\Fortify\Http\Responses\LogoutResponse;

/**
 * Fortify's sign-out response, and the next page tells the browser to clear its history, so Back on a shared device
 * can't show the signed-out user's pages.
 */
class ClearHistoryLogoutResponse extends LogoutResponse
{
    /**
     * @param  Request  $request
     */
    public function toResponse($request)
    {
        Inertia::clearHistory();

        return parent::toResponse($request);
    }
}
