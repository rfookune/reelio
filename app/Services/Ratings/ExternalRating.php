<?php

namespace App\Services\Ratings;

use App\Services\Ratings\Enums\RatingSourceName;
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

    /**
     * The rating as pages show it, credited to its source.
     *
     * @return array{source: string, name: string, value: float, voteCount: int}
     */
    public function toArray(): array
    {
        return ['source' => $this->source->value, 'name' => $this->source->label(), 'value' => $this->value, 'voteCount' => $this->voteCount];
    }
}
