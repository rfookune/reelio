<?php

namespace App\Http\Controllers;

use App\Http\Exceptions\MovieRemoved;
use App\Http\Support\MoviePageDetails;
use App\Services\Catalog\Catalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MovieCastController extends Controller
{
    /**
     * The Movie's whole billed cast. Like the Movie page, a stale slug redirects, a Removed Movie is a 410 and the
     * details are refreshed when due, but no view is recorded: the visitor came from the Movie page. A Movie without
     * details is refreshed before its slug is checked, and is a 503 while that fails.
     */
    public function __invoke(Request $request, string $slug, string $id, Catalog $catalog): Response|RedirectResponse
    {
        $movieId = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        abort_if($movieId === false, 404);
        $movie = $catalog->find($movieId);
        throw_if($movie->isRemoved(), MovieRemoved::class, $movie, $request->user());
        $details = new MoviePageDetails($catalog, $movie, $request->user());
        $details->restoreIfCleared();

        $routeParameters = ['slug' => $movie->slug ?? 'movie', 'id' => $movie->id];

        if ($slug !== $routeParameters['slug']) {
            return redirect()->route('movies.cast', $routeParameters, 301);
        }

        $result = $details->result();

        return Inertia::render('movies/Cast', [
            'imageBaseUrl' => config('catalog.image_base_url'),
            'canonicalUrl' => route('movies.cast', $routeParameters),
            'movieUrl' => $movie->canonicalUrl(),
            'movie' => [
                'title' => $movie->title,
                'year' => $movie->release_date?->year,
                'cast' => $result->value->cast ?? [],
                'detailsUnavailable' => $result->isUnavailable(),
            ],
        ]);
    }
}
