<?php

namespace App\Ratings;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class TmdbRatingSource implements RatingSource
{
    public function ratingsFor(Collection $movies): array
    {
        $ratings = [];

        foreach ($movies as $movie) {
            if ($movie->vote_average === null || $movie->vote_count === null || $movie->vote_count <= 0) {
                continue;
            }

            $ratings[$movie->id] = new ExternalRating(
                RatingSourceName::Tmdb,
                $movie->vote_average,
                $movie->vote_count,
                $movie->synced_at ?? CarbonImmutable::instance($movie->updated_at),
            );
        }

        return $ratings;
    }
}
