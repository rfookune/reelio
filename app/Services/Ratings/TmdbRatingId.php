<?php

namespace App\Services\Ratings;

/** TMDB numbers identify a Title only within its kind. */
class TmdbRatingId
{
    public static function for(string $kind, int $tmdbId): string
    {
        return $kind.':'.$tmdbId;
    }
}
