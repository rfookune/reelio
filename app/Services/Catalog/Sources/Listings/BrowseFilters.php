<?php

namespace App\Services\Catalog\Sources\Listings;

/**
 * The filters a page of Browse is narrowed by, already checked against what the source offers, and the order it is
 * sorted in. A filter that is null or empty isn't applied; a requested sort that is null is the default of the
 * filters, which `sort()` gives. Streaming services are matched any-of, by subscription or free offer, in the
 * `country`, which only matters while there are any.
 */
readonly class BrowseFilters
{
    /** @param  list<int>  $streamingServiceIds */
    public function __construct(
        public ?int $genreId = null,
        public ?BrowseSort $requestedSort = null,
        public array $streamingServiceIds = [],
        public ?string $country = null,
        public bool $highlyRated = false,
        public ?Era $era = null,
    ) {}

    /** Whether any filter narrows Browse; the sort doesn't. */
    public function isNarrowed(): bool
    {
        return $this->genreId !== null || $this->streamingServiceIds !== [] || $this->highlyRated || $this->era !== null;
    }

    /** The order chosen, else the default of the filters. */
    public function sort(): BrowseSort
    {
        return $this->requestedSort ?? BrowseSort::defaultFor($this);
    }

    /** What tells one set of filters from another, for a cache key. */
    public function cacheKey(): string
    {
        return json_encode([
            'genre' => $this->genreId,
            'sort' => $this->sort()->value,
            'services' => $this->streamingServiceIds,
            'country' => $this->streamingServiceIds === [] ? null : $this->country,
            'highly_rated' => $this->highlyRated,
            'era' => $this->era?->value,
        ], JSON_THROW_ON_ERROR);
    }
}
