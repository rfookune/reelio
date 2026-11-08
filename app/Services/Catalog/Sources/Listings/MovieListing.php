<?php

namespace App\Services\Catalog\Sources\Listings;

use App\Enums\TitleKind;

/**
 * One Movie as a Catalog source's list shows it: what's needed to place it in a section, without the details a
 * Movie page needs. A search can also list a TV Show, whose Name is the `title` and first-air date the `releaseDate`.
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
        public TitleKind $kind = TitleKind::Movie,
    ) {}
}
