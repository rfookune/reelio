<?php

namespace App\Services\Ratings\Sources;

use App\Models\Title;
use App\Services\Ratings\ExternalRating;
use Illuminate\Support\Collection;

interface RatingSource
{
    /**
     * One batch lookup for many Titles. A Title without a rating is absent from the result, never a placeholder.
     *
     * @template TTitle of Title
     *
     * @param  Collection<int, TTitle>  $titles
     * @return array<int, ExternalRating> keyed by Title id
     */
    public function ratingsFor(Collection $titles): array;
}
