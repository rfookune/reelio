<?php

namespace App\Http\Controllers;

use App\Actions\RecordEvent;
use App\Http\Exceptions\MovieRemoved;
use App\Http\Support\MoviePageDetails;
use App\Http\Support\UserMovies;
use App\Http\Support\VisitorCountry;
use App\Models\Availability;
use App\Models\Movie;
use App\Services\Catalog\Catalog;
use App\Services\Catalog\CatalogResult;
use App\Services\Catalog\Enums\OfferType;
use App\Services\Catalog\Sources\MovieDetails;
use App\Services\Ratings\ExternalRating;
use App\Services\Ratings\Ratings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Inertia\Support\Header;

class MovieController extends Controller
{
    private const AVAILABILITY_OUT_OF_DATE_DAYS = 7;

    /**
     * Records a view of the Movie unless the request is a partial reload, such as after changing the country,
     * which refreshes the page the visitor is already on. A Removed Movie, including one the refresh in this
     * request finds removed, is a 410 at any slug and records no view. The props that need the Catalog details are
     * lazy, so a partial reload that asks for none of them never fetches the details (see `MoviePageDetails`).
     */
    public function show(Request $request, string $slug, string $id, Catalog $catalog, RecordEvent $recordEvent, Ratings $ratings): Response|RedirectResponse
    {
        $movieId = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        abort_if($movieId === false, 404);
        $movie = $catalog->find($movieId);
        throw_if($movie->isRemoved(), MovieRemoved::class, $movie);
        $referrer = $request->query('referrer');

        if ($slug !== ($movie->slug ?? 'movie')) {
            return redirect($movie->canonicalUrl(is_string($referrer) ? $referrer : null), 301);
        }

        $details = new MoviePageDetails($catalog, $movie);

        if (! $request->hasHeader(Header::PARTIAL_COMPONENT)) {
            $details->result();
            $catalog->recordView($movie);
            $referrer = in_array($referrer, ['discover', 'search', 'watchlist', 'watched', 'picker', 'direct'], true) ? $referrer : 'direct';
            $recordEvent->handle($request->user(), 'movie_viewed', ['movie_id' => $movie->id, 'referrer' => $referrer]);
        }

        return Inertia::render('movies/Show', [
            'imageBaseUrl' => config('catalog.image_base_url'),
            'whereToWatch' => fn (): array => $this->whereToWatch($movie, VisitorCountry::resolve($request), $details->refreshFailed()),
            'canonicalUrl' => $movie->canonicalUrl(),
            'userMovies' => UserMovies::for($request->user(), [$movie]),
            'movie' => fn (): array => $this->movie($movie, $details->result(), $ratings),
        ]);
    }

    /**
     * The Movie's stored fields, its External ratings and its Catalog details, which are empty when unavailable.
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
            'cast' => $details->cast ?? [],
            'certification' => $details?->certification,
            'usReleaseDate' => $details?->usReleaseDate,
        ];
    }

    /**
     * The Where to watch block for one country: the non-empty offer-type groups in tab order, each listing its
     * Streaming services in `display_priority` order, and the freshness line from `synced_at`. It's flagged as
     * possibly out of date when the Movie is still due a refresh after this view, which means its refresh failed,
     * or past seven calendar days since the last sync.
     *
     * @return array{country: string, countryName: string, countryUnsupported: bool, countries: list<array{code: string, name: string}>, groups: list<array{type: string, label: string, caption: ?string, services: list<array{id: int, name: string, logoPath: ?string}>}>, updated: ?string, outOfDate: bool}
     */
    private function whereToWatch(Movie $movie, VisitorCountry $visitorCountry, bool $refreshFailed): array
    {
        $country = $visitorCountry->code;
        $offers = $movie->availabilities()
            ->with('streamingService')
            ->where('country', $country)
            ->orderBy('display_priority')
            ->orderBy('streaming_service_id')
            ->get()
            ->groupBy(fn (Availability $availability): string => $availability->offer_type->value);
        $groups = [];

        foreach (OfferType::cases() as $offerType) {
            if (! $offers->has($offerType->value)) {
                continue;
            }

            $groups[] = [
                'type' => $offerType->value,
                'label' => $offerType->label(),
                'caption' => $offerType->caption(),
                'services' => array_values($offers[$offerType->value]->map(fn (Availability $availability): array => [
                    'id' => $availability->streamingService->id,
                    'name' => $availability->streamingService->name,
                    'logoPath' => $availability->streamingService->logo_path,
                ])->all()),
            ];
        }

        $daysSinceSync = $movie->synced_at === null ? null : (int) $movie->synced_at->startOfDay()->diffInDays(now()->startOfDay());

        return [
            'country' => $country,
            'countryName' => VisitorCountry::name($country),
            'countryUnsupported' => $visitorCountry->detectedUnsupported,
            'countries' => VisitorCountry::choices(),
            'groups' => $groups,
            'updated' => $this->updatedLabel($daysSinceSync),
            'outOfDate' => $refreshFailed || ($daysSinceSync !== null && $daysSinceSync > self::AVAILABILITY_OUT_OF_DATE_DAYS),
        ];
    }

    private function updatedLabel(?int $days): ?string
    {
        if ($days === null) {
            return null;
        }

        return match (true) {
            $days <= 0 => 'Updated today',
            $days === 1 => 'Updated yesterday',
            default => "Updated {$days} days ago",
        };
    }
}
