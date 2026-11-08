<?php

namespace App\Http\Controllers;

use App\Actions\RecordEvent;
use App\Enums\AnalyticsEvent;
use App\Enums\Surface;
use App\Http\Exceptions\TitleRemoved;
use App\Http\Support\MoviePageDetails;
use App\Http\Support\UserMovies;
use App\Http\Support\VisitorCountry;
use App\Http\Support\WhereToWatch;
use App\Models\Movie;
use App\Services\Catalog\Catalog;
use App\Services\Catalog\CatalogResult;
use App\Services\Catalog\Sources\Details\MovieDetails;
use App\Services\Ratings\ExternalRating;
use App\Services\Ratings\Ratings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Inertia\Support\Header;

class MovieController extends Controller
{
    /**
     * Records a view of the Movie unless the request is a partial reload, such as after changing the country,
     * which refreshes the page the visitor is already on. A Removed Movie, including one the refresh in this
     * request finds removed, is a 410 at any slug and records no view. A Movie without details, as one cleared at
     * TMDB's cap, is refreshed before its slug is checked, since only the refresh restores the slug; while that
     * fails, it is a 503 at any slug, partial reloads included, and records no view. Otherwise the props that need
     * the Catalog details are lazy, so a partial reload that asks for none of them never fetches the details (see
     * `MoviePageDetails`).
     */
    public function show(Request $request, string $slug, string $id, Catalog $catalog, RecordEvent $recordEvent, Ratings $ratings): Response|RedirectResponse
    {
        $title = $catalog->findTitle($id);
        if (! $title instanceof Movie) {
            return redirect($title->canonicalUrl(), 301);
        }
        $movie = $title;
        throw_if($movie->isRemoved(), TitleRemoved::class, $movie, $request->user());
        $referrer = Surface::movieReferrerFrom($request->query('referrer'));
        $details = new MoviePageDetails($catalog, $movie, $request->user());
        $details->restoreIfCleared();

        if ($slug !== ($movie->slug ?? 'movie')) {
            return redirect($movie->canonicalUrl($referrer), 301);
        }

        if (! $request->hasHeader(Header::PARTIAL_COMPONENT)) {
            $details->result();
            $catalog->recordView($movie);
            $recordEvent->handle($request->user(), AnalyticsEvent::MovieViewed, ['movie_id' => $movie->id, 'referrer' => ($referrer ?? Surface::Direct)->value]);
        }

        return Inertia::render('movies/Show', [
            'imageBaseUrl' => config('catalog.image_base_url'),
            'whereToWatch' => fn (): array => WhereToWatch::for($movie, VisitorCountry::resolve($request), $details->refreshFailed()),
            'canonicalUrl' => $movie->canonicalUrl(),
            'castUrl' => route('movies.cast', ['slug' => $movie->slug ?? 'movie', 'id' => $movie->id]),
            'userMovies' => UserMovies::for($request->user(), [$movie]),
            'movie' => fn (): array => $this->movie($movie, $details->result(), $ratings),
        ]);
    }

    /**
     * The Movie's stored fields, its External ratings and its Catalog details, which are empty when unavailable. The
     * cast is cut to the top billed; `castCount` says whether the full cast page lists more.
     *
     * @param  CatalogResult<MovieDetails>  $result
     * @return array<string, mixed>
     */
    private function movie(Movie $movie, CatalogResult $result, Ratings $ratings): array
    {
        $details = $result->value;

        return [
            'id' => $movie->id,
            'tmdbId' => $movie->tmdb_id,
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
            'ratings' => array_map(fn (ExternalRating $rating): array => $rating->toArray(), $ratings->ratingsFor(collect([$movie]))[$movie->id]),
            'trailer' => $details?->trailer,
            'detailsUnavailable' => $result->isUnavailable(),
            'directors' => $details->directors ?? [],
            'writers' => $details->writers ?? [],
            'cast' => array_slice($details->cast ?? [], 0, config('catalog.movie_page.cast_limit')),
            'castCount' => count($details->cast ?? []),
            'certification' => $details?->certification,
            'usReleaseDate' => $details?->usReleaseDate,
        ];
    }
}
