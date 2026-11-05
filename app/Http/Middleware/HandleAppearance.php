<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class HandleAppearance
{
    /**
     * The appearances a visitor can choose; anything else counts as no choice.
     *
     * @var list<string>
     */
    private const APPEARANCES = ['light', 'dark', 'system'];

    /**
     * The appearance used until the visitor chooses one.
     */
    private const DEFAULT_APPEARANCE = 'dark';

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        View::share('appearance', $this->appearance($request));

        return $next($request);
    }

    /**
     * The visitor's chosen appearance. The cookie is unencrypted and echoed
     * into an inline script, so only known values are accepted.
     */
    private function appearance(Request $request): string
    {
        $appearance = $request->cookie('appearance');

        return in_array($appearance, self::APPEARANCES, true)
            ? $appearance
            : self::DEFAULT_APPEARANCE;
    }
}
