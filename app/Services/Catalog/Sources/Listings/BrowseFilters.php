<?php

namespace App\Services\Catalog\Sources\Listings;

/**
 * The filters a page of Browse is narrowed by, already checked against what the source offers, and the order it is
 * sorted in. A filter that is null isn't applied; a requested sort that is null is the default of the
 * filters, which `sort()` gives.
 */
readonly class BrowseFilters
{
    public function __construct(public ?int $genreId = null, public ?BrowseSort $requestedSort = null) {}

    /** Whether any filter narrows Browse; the sort doesn't. */
    public function isNarrowed(): bool
    {
        return $this->genreId !== null;
    }

    /** The order chosen, else the default of the filters. */
    public function sort(): BrowseSort
    {
        return $this->requestedSort ?? BrowseSort::defaultFor($this);
    }

    /** What tells one set of filters from another, for a cache key. */
    public function cacheKey(): string
    {
        return json_encode(['genre' => $this->genreId, 'sort' => $this->sort()->value], JSON_THROW_ON_ERROR);
    }
}
