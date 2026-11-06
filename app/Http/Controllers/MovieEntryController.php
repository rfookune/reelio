<?php

namespace App\Http\Controllers;

use App\Http\MovieByCatalogId;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MovieEntryController extends Controller
{
    public function __invoke(Request $request, string $tmdbId, MovieByCatalogId $movies): RedirectResponse
    {
        $movie = $movies->findOrCreate($request, $tmdbId);
        $referrer = $request->query('referrer');

        return redirect($movie->canonicalUrl(is_string($referrer) ? $referrer : null), 301)
            ->header('X-Robots-Tag', 'noindex');
    }
}
