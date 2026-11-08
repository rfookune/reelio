<?php

namespace App\Http\Controllers;

use App\Http\Exceptions\DetailsUnavailable;
use App\Http\Exceptions\TitleRemoved;
use App\Models\TvShow;
use App\Services\Catalog\Catalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TvShowCastController extends Controller
{
    /**
     * The TV Show's whole series-wide billed cast and crew. Like the TV Show page, a stale slug redirects, a Removed
     * TV Show is a 410 and the details are refreshed when due, but no view is recorded: the visitor came from the
     * TV Show page. A TV Show without details is refreshed before its slug is checked, and is a 503 while that fails.
     */
    public function __invoke(Request $request, string $slug, string $id, Catalog $catalog): Response|RedirectResponse
    {
        abort_unless(config('catalog.tv_shows.enabled'), 404);
        $tvShow = $catalog->findTitle($id);
        abort_unless($tvShow instanceof TvShow, 404);
        throw_if($tvShow->isRemoved(), TitleRemoved::class, $tvShow, $request->user());

        if ($tvShow->hasSourceDetails() && $slug !== $tvShow->slug) {
            return redirect()->route('tv-shows.cast', ['slug' => $tvShow->slug, 'id' => $tvShow->id], 301);
        }

        $result = $catalog->tvShowDetails($tvShow);
        throw_if($result->isRemoved(), TitleRemoved::class, $tvShow, $request->user());
        throw_if($result->isUnavailable() || ! $tvShow->hasSourceDetails(), DetailsUnavailable::class, $tvShow, $request->user());

        $routeParameters = ['slug' => $tvShow->slug ?? 'tv-show', 'id' => $tvShow->id];

        if ($slug !== $routeParameters['slug']) {
            return redirect()->route('tv-shows.cast', $routeParameters, 301);
        }

        $pageData = $catalog->tvShowPageData($tvShow);

        return Inertia::render('tv-shows/Cast', [
            'imageBaseUrl' => config('catalog.image_base_url'),
            'canonicalUrl' => route('tv-shows.cast', $routeParameters),
            'tvShowUrl' => $tvShow->canonicalUrl(),
            'tvShow' => [
                'title' => $tvShow->title,
                'year' => $tvShow->details->first_air_date?->year,
                'cast' => $pageData->cast ?? [],
                'crew' => $pageData->crew ?? [],
                'detailsUnavailable' => $pageData === null,
            ],
        ]);
    }
}
