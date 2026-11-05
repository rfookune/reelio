<?php

namespace App\Http\Controllers;

use App\Catalog\Catalog;
use App\Catalog\CreationLimited;
use App\Catalog\SourceUnavailable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MovieEntryController extends Controller
{
    public function __invoke(Request $request, string $tmdbId, Catalog $catalog): RedirectResponse
    {
        $catalogId = filter_var($tmdbId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        abort_if($catalogId === false, 404);

        try {
            $actor = $request->user() !== null ? 'user:'.$request->user()->id : 'ip:'.$request->ip();
            $movie = $catalog->findOrCreate($catalogId, $actor);
        } catch (CreationLimited $exception) {
            abort(429, $exception->getMessage(), ['Retry-After' => $exception->retryAfter, 'X-Robots-Tag' => 'noindex']);
        } catch (SourceUnavailable) {
            abort(503, 'Movie details are temporarily unavailable.', ['Retry-After' => 30, 'X-Robots-Tag' => 'noindex']);
        }
        abort_if($movie === null, 404);

        $referrer = $request->query('referrer');

        return redirect($movie->canonicalUrl(is_string($referrer) ? $referrer : null), 301)
            ->header('X-Robots-Tag', 'noindex');
    }
}
