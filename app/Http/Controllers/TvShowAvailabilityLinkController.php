<?php

namespace App\Http\Controllers;

use App\Actions\RecordEvent;
use App\Http\Exceptions\DetailsUnavailable;
use App\Http\Support\RateLimitKey;
use App\Http\Support\VisitorCountry;
use App\Services\Catalog\Catalog;
use App\Services\Catalog\Exceptions\CreationLimited;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TvShowAvailabilityLinkController extends Controller
{
    public function __invoke(Request $request, string $tmdbId, Catalog $catalog, RecordEvent $recordEvent): RedirectResponse
    {
        abort_unless(config('catalog.tv_shows.enabled'), 404);
        $country = $request->query('country');
        abort_unless(VisitorCountry::isSupported($country), 404);

        $catalogId = filter_var($tmdbId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        abort_if($catalogId === false, 404);
        try {
            $result = $catalog->findOrCreateTvShow($catalogId, RateLimitKey::for($request));
        } catch (CreationLimited $exception) {
            abort(429, $exception->getMessage(), ['Retry-After' => $exception->retryAfter, 'X-Robots-Tag' => 'noindex, nofollow']);
        }
        throw_if($result->isUnavailable(), DetailsUnavailable::class);
        abort_if($result->value === null, 404);
        $tvShow = $result->value;

        $recordEvent->handle($request->user(), 'availability_link_clicked', ['title_id' => $tvShow->id, 'kind' => 'tv', 'country' => $country]);

        return redirect()->away(sprintf('https://www.themoviedb.org/tv/%d/watch?locale=%s', $tvShow->tmdb_id, $country))
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
