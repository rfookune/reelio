<?php

namespace App\Http\Controllers;

use App\Enums\Surface;
use App\Http\Support\TitleActions\MovieByCatalogId;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MovieEntryController extends Controller
{
    public function __invoke(Request $request, string $tmdbId, MovieByCatalogId $movies): RedirectResponse
    {
        $movie = $movies->findOrCreate($request, $tmdbId);
        $referrer = Surface::movieReferrerFrom($request->query('referrer'));

        return redirect($movie->canonicalUrl($referrer), 301)
            ->header('X-Robots-Tag', 'noindex');
    }
}
