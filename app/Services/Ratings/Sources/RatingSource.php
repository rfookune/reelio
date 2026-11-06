<?php

namespace App\Services\Ratings\Sources;

use App\Models\Movie;
use App\Services\Ratings\ExternalRating;
use Illuminate\Support\Collection;

interface RatingSource
{
    /**
     * One batch lookup for many Movies. A Movie without a rating is absent from the result, never a placeholder.
     *
     * @param  Collection<int, Movie>  $movies
     * @return array<int, ExternalRating> keyed by Movie id
     */
    public function ratingsFor(Collection $movies): array;
}
