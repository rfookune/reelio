<?php

namespace App\Http\Support;

use Illuminate\Http\Request;

/**
 * The routes Reelio renders on the server and, with the indexing flag on, lets crawlers index: Discover, Movie pages
 * and the About, Privacy, Terms and Accessibility pages (`config('seo.public_routes')`).
 */
class PublicRoutes
{
    public static function includes(Request $request): bool
    {
        return in_array($request->route()?->getName(), (array) config('seo.public_routes'), true);
    }
}
