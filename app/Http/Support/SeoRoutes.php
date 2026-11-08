<?php

namespace App\Http\Support;

use Illuminate\Http\Request;

/**
 * Which routes are rendered on the server (`config('seo.ssr_routes')`) and which are indexable when the indexing flag
 * is on (`config('seo.indexable_routes')`). The two are separate decisions: a page can be server-rendered without
 * being indexable.
 */
class SeoRoutes
{
    public static function isServerRendered(Request $request): bool
    {
        return self::listed($request, 'seo.ssr_routes');
    }

    public static function isIndexable(Request $request): bool
    {
        return self::listed($request, 'seo.indexable_routes');
    }

    private static function listed(Request $request, string $key): bool
    {
        return in_array($request->route()?->getName(), (array) config($key), true);
    }
}
