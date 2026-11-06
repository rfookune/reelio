<?php

namespace App\Http\Controllers;

use App\Catalog\Catalog;
use App\Catalog\CreationLimited;
use App\Http\Exceptions\DetailsUnavailable;
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
            $result = $catalog->findOrCreate($catalogId, $actor);
        } catch (CreationLimited $exception) {
            abort(429, $exception->getMessage(), ['Retry-After' => $exception->retryAfter, 'X-Robots-Tag' => 'noindex']);
        }

        throw_if($result->isUnavailable(), DetailsUnavailable::class);
        $movie = $result->value;
        abort_if($movie === null, 404);

        $referrer = $request->query('referrer');

        return redirect($movie->canonicalUrl(is_string($referrer) ? $referrer : null), 301)
            ->header('X-Robots-Tag', 'noindex');
    }
}
