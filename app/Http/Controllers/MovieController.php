<?php

namespace App\Http\Controllers;

use App\Actions\RecordEvent;
use App\Catalog\Catalog;
use App\Catalog\CatalogStatus;
use App\Catalog\OfferType;
use App\Models\Availability;
use App\Models\Movie;
use App\Ratings\ExternalRating;
use App\Ratings\Ratings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Locale;

class MovieController extends Controller
{
    private const AVAILABILITY_OUT_OF_DATE_DAYS = 7;

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
            'whereToWatch' => $this->whereToWatch($movie, config('catalog.default_country'), $result->status === CatalogStatus::Stale),
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

    /**
     * The Where to watch block for one country: the non-empty offer-type groups in tab order, each listing its
     * Streaming services in `display_priority` order, and the freshness line from `synced_at`. It's flagged as
     * possibly out of date past seven calendar days, or when the Movie's details could only be served stale.
     *
     * @return array{country: string, countryName: string, groups: list<array{type: string, label: string, caption: ?string, services: list<array{id: int, name: string, logoPath: ?string}>}>, updated: ?string, outOfDate: bool}
     */
    private function whereToWatch(Movie $movie, string $country, bool $detailsStale): array
    {
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
            'countryName' => Locale::getDisplayRegion("-{$country}", 'en') ?: $country,
            'groups' => $groups,
            'updated' => $this->updatedLabel($daysSinceSync),
            'outOfDate' => $detailsStale || ($daysSinceSync !== null && $daysSinceSync > self::AVAILABILITY_OUT_OF_DATE_DAYS),
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
