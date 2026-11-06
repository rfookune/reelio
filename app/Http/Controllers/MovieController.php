<?php

namespace App\Http\Controllers;

use App\Actions\RecordEvent;
use App\Catalog\Catalog;
use App\Ratings\ExternalRating;
use App\Ratings\Ratings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MovieController extends Controller
{
    public function show(Request $request, string $slug, string $id, Catalog $catalog, RecordEvent $recordEvent, Ratings $ratings): Response|RedirectResponse
    {
        $movieId = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        abort_if($movieId === false, 404);
        $movie = $catalog->find($movieId);
        $referrer = $request->query('referrer');

        if ($slug !== ($movie->slug ?? 'movie')) {
            return redirect($movie->canonicalUrl(is_string($referrer) ? $referrer : null), 301);
        }

        $catalog->recordView($movie);
        $result = $catalog->details($movie);
        $details = $result->value;
        $referrer = in_array($referrer, ['discover', 'search', 'watchlist', 'watched', 'picker', 'direct'], true) ? $referrer : 'direct';
        $recordEvent->handle($request->user(), 'movie_viewed', ['movie_id' => $movie->id, 'referrer' => $referrer]);

        return Inertia::render('movies/Show', [
            'imageBaseUrl' => config('catalog.image_base_url'),
            'canonicalUrl' => $movie->canonicalUrl(),
            'movie' => [
                'id' => $movie->id,
                'title' => $movie->title,
                'originalTitle' => $movie->original_title,
                'originalLanguage' => $movie->original_language,
                'year' => $movie->release_date?->year,
                'releaseDate' => $movie->release_date?->toDateString(),
                'runtime' => $movie->runtime,
                'tagline' => $movie->tagline,
                'overview' => $movie->overview,
                'posterPath' => $movie->poster_path,
                'backdropPath' => $movie->backdrop_path,
                'genres' => $movie->genres()->orderBy('name')->pluck('name')->all(),
                'ratings' => array_map(fn (ExternalRating $rating): array => [
                    'source' => $rating->source->value,
                    'name' => $rating->source->label(),
                    'value' => $rating->value,
                    'voteCount' => $rating->voteCount,
                ], $ratings->ratingsFor(collect([$movie]))[$movie->id]),
                'trailer' => $details?->trailer,
                'detailsUnavailable' => $result->isUnavailable(),
                'directors' => $details->directors ?? [],
                'writers' => $details->writers ?? [],
                'cast' => $details->cast ?? [],
                'certification' => $details?->certification,
                'usReleaseDate' => $details?->usReleaseDate,
            ],
        ]);
    }
}
