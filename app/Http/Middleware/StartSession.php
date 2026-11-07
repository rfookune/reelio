<?php

namespace App\Http\Middleware;

use Illuminate\Contracts\Session\Session;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession as BaseStartSession;

/**
 * The framework's session start, except that Search is remembered as the previous page by its path alone, because
 * search text is never stored.
 */
class StartSession extends BaseStartSession
{
    /**
     * @param  Session  $session
     */
    protected function storeCurrentUrl(Request $request, $session): void
    {
        parent::storeCurrentUrl($request, $session);

        if ($request->routeIs('search') && $session->previousUrl() === $request->fullUrl()) {
            $session->setPreviousUrl($request->url());
        }
    }
}
