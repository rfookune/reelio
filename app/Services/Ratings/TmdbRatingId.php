<?php

namespace App\Services\Ratings;

use App\Enums\TitleKind;

/** TMDB numbers identify a Title only within its kind. */
class TmdbRatingId
{
    public static function for(TitleKind $kind, int $tmdbId): string
    {
        return $kind->value.':'.$tmdbId;
    }
}
