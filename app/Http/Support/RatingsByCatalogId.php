<?php

namespace App\Http\Support;

use App\Models\Movie;
use App\Services\Ratings\ExternalRating;
use App\Services\Ratings\Ratings;

/**
 * External ratings for listed Movies, with a row or not, from one batch `Ratings` call. The batch lookup is keyed
 * by Movie id, so each Movie goes in as a stand-in whose id is its catalog id, keeping the two id spaces apart; a
 * Movie without a row still shows the TMDB score recorded when it was listed.
 */
class RatingsByCatalogId
{
    /**
     * @param  array<int, ?string>  $imdbIds  each Movie's IMDb id, if known, keyed by catalog id
     * @return array<int, list<ExternalRating>> keyed by catalog id
     */
    public static function for(Ratings $ratings, array $imdbIds): array
    {
        return $ratings->ratingsFor(collect($imdbIds)->map(fn (?string $imdbId, int $tmdbId): Movie => (new Movie)->forceFill([
            'id' => $tmdbId,
            'tmdb_id' => $tmdbId,
            'imdb_id' => $imdbId,
        ]))->values());
    }
}
