<?php

namespace App\Ratings;

use App\Ratings\Enums\RatingSourceName;
use Carbon\CarbonImmutable;

/**
 * A score for a Movie published by one Rating source, on a 0-10 scale. It always stands alone with its source.
 */
readonly class ExternalRating
{
    public function __construct(
        public RatingSourceName $source,
        public float $value,
        public int $voteCount,
        public CarbonImmutable $asOf,
    ) {}
}
