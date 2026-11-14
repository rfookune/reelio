<?php

namespace App\Http\Support\Cards;

use App\Enums\TitleKind;
use App\Models\Movie;
use App\Services\Ratings\ExternalRating;
use App\Services\Ratings\Ratings;

/**
 * External ratings for listed Movies or TV Shows, with a row or not, from one batch `Ratings` call. The batch lookup is keyed
 * by Title id, so each title goes in as a stand-in whose id is its catalog id, keeping the two id spaces apart; a
 * title without a row still shows the TMDB score recorded when it was listed. All are of one kind, since a Movie
 * and a TV Show may share a catalog id.
 */
class RatingsByCatalogId
{
    /**
     * @param  array<int, ?string>  $imdbIds  each title's IMDb id, if known, keyed by catalog id
     * @return array<int, list<ExternalRating>> keyed by catalog id
     */
    public static function for(Ratings $ratings, array $imdbIds, TitleKind $kind = TitleKind::Movie): array
    {
        return $ratings->ratingsFor(collect($imdbIds)->map(fn (?string $imdbId, int $tmdbId): Movie => (new Movie)->forceFill([
            'kind' => $kind,
            'id' => $tmdbId,
            'tmdb_id' => $tmdbId,
            'imdb_id' => $imdbId,
        ]))->values());
    }
}
