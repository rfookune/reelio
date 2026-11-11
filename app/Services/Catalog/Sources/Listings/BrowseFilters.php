<?php

namespace App\Services\Catalog\Sources\Listings;

/**
 * The filters a page of Browse is narrowed by, already checked against what the source offers. A filter that is
 * null isn't applied.
 */
readonly class BrowseFilters
{
    public function __construct(public ?int $genreId = null) {}

    /** What tells one set of filters from another, for a cache key. */
    public function cacheKey(): string
    {
        return json_encode(['genre' => $this->genreId], JSON_THROW_ON_ERROR);
    }
}
