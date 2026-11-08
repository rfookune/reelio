<?php

namespace App\Services\Catalog\Sources\Listings;

/**
 * One Movie as a Catalog source's list shows it: what's needed to place it in a section, without the details a
 * Movie page needs.
 */
readonly class MovieListing
{
    public function __construct(
        public int $tmdbId,
        public ?string $title = null,
        public ?string $overview = null,
        public ?string $releaseDate = null,
        public ?string $posterPath = null,
        public ?string $backdropPath = null,
        public ?float $voteAverage = null,
        public ?int $voteCount = null,
    ) {}
}
