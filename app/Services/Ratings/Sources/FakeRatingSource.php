<?php

namespace App\Services\Ratings\Sources;

use App\Services\Ratings\ExternalRating;
use Illuminate\Support\Collection;

class FakeRatingSource implements RatingSource
{
    /** Batches requested so far, so tests can show there are no per-card lookups. */
    public int $batches = 0;

    /** @param array<int, ExternalRating> $ratings keyed by Title id */
    public function __construct(private array $ratings = []) {}

    public function ratingsFor(Collection $titles): array
    {
        $this->batches++;

        return array_intersect_key($this->ratings, array_flip($titles->pluck('id')->all()));
    }
}
