<?php

namespace App\Http\Controllers;

use App\Actions\RecordEvent;
use App\Http\Requests\MoviePickerRequest;
use App\Http\Support\MoviePicker;
use App\Http\Support\VisitorCountry;
use App\Http\Support\WhereToWatch;
use App\Models\Genre;
use App\Models\Movie;
use App\Services\Catalog\Enums\OfferType;
use App\Services\Ratings\ExternalRating;
use App\Services\Ratings\Ratings;
use Illuminate\Http\JsonResponse;

/**
 * The Movie picker's one endpoint, called from the Watchlist's dialog: a random candidate with what's needed to
 * decide on it, the pool size, and the genres the filter offers. The Watchlist itself never leaves the server, and
 * the pick is read from storage alone: it never refreshes the Movie or counts as a view.
 */
class MoviePickerController extends Controller
{
    public function __invoke(MoviePickerRequest $request, Ratings $ratings, RecordEvent $recordEvent): JsonResponse
    {
        $picker = new MoviePicker($request->user());
        ['movie' => $movie, 'poolSize' => $poolSize] = $picker->pick($request->criteria());
        $recordEvent->handle($request->user(), 'movie_picker_used', ['pool_size' => $poolSize]);

        return response()->json([
            'poolSize' => $poolSize,
            'genres' => $picker->genres(),
            'movie' => $movie === null ? null : $this->movie($movie, $ratings, VisitorCountry::resolve($request)),
        ])->header('X-Robots-Tag', 'noindex');
    }

    /**
     * The pick's stored details, its External ratings, and where it streams for free or by subscription in the
     * user's country. With no refresh attempted, only the sync's age marks Availability as possibly out of date.
     *
     * @return array{tmdbId: int, title: ?string, year: ?int, runtime: ?int, genres: list<string>, overview: ?string, posterPath: ?string, ratings: list<array{source: string, name: string, value: float, voteCount: int}>, href: string, whereToWatch: array{country: string, countryName: string, groups: list<array{type: string, label: string, caption: ?string, services: list<array{id: int, name: string, logoPath: ?string}>}>, updated: ?string, outOfDate: bool}}
     */
    private function movie(Movie $movie, Ratings $ratings, VisitorCountry $country): array
    {
        $whereToWatch = WhereToWatch::for($movie, $country, offerTypes: [OfferType::Stream, OfferType::Free]);

        return [
            'tmdbId' => $movie->tmdb_id,
            'title' => $movie->title,
            'year' => $movie->release_date?->year,
            'runtime' => $movie->runtime,
            'genres' => array_values($movie->genres()->orderBy('name')->get()->map(fn (Genre $genre): string => $genre->name)->all()),
            'overview' => $movie->overview,
            'posterPath' => $movie->poster_path,
            'ratings' => array_map(fn (ExternalRating $rating): array => $rating->toArray(), $ratings->ratingsFor(collect([$movie]))[$movie->id]),
            'href' => $movie->canonicalUrl('picker'),
            'whereToWatch' => [
                'country' => $whereToWatch['country'],
                'countryName' => $whereToWatch['countryName'],
                'groups' => $whereToWatch['groups'],
                'updated' => $whereToWatch['updated'],
                'outOfDate' => $whereToWatch['outOfDate'],
            ],
        ];
    }
}
