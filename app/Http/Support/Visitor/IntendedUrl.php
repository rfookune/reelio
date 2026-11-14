<?php

namespace App\Http\Support\Visitor;

use Illuminate\Http\Request;

/**
 * Where sign-in returns a guest: the page the session last recorded on a page load, never a header or request
 * parameter. One guard accepts only a relative path starting with a single `/`, so it can't send anyone off-site.
 */
class IntendedUrl
{
    private const LAST_PAGE_KEY = 'guest.last_page';

    /**
     * Records the page a guest just loaded, as its path and query; Search as its path alone, because search text
     * is never stored.
     */
    public static function recordPage(Request $request): void
    {
        $request->session()->put(self::LAST_PAGE_KEY, $request->routeIs('search') ? $request->getPathInfo() : $request->getRequestUri());
    }

    /** Sets the intended URL, which sign-in and registration redirect to, to the page last recorded. */
    public static function setToLastPage(Request $request): void
    {
        redirect()->setIntendedUrl(self::guard($request->session()->get(self::LAST_PAGE_KEY)));
    }

    /**
     * The path, if it is relative and starts with a single `/`; else Discover. `//host` and `/\host` are rejected
     * because browsers read both as another host, and so is any whitespace or control character, which browsers
     * strip or reinterpret.
     */
    private static function guard(mixed $path): string
    {
        if (is_string($path) && preg_match('#^/(?![/\\\\])[^\x00-\x20\x7F]*$#D', $path) === 1) {
            return $path;
        }

        return route('home', absolute: false);
    }
}
