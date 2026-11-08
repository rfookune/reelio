<?php

namespace App\Services\Catalog\Sources\Listings;

use Error;

/**
 * One page of a Catalog source's title search, in its order, with how many pages and results the search has.
 */
readonly class SearchResults
{
    /** @param  list<MovieListing>  $listings */
    public function __construct(
        public array $listings,
        public int $totalPages,
        public int $totalResults,
    ) {}

    /**
     * Cache-safe form: the cache store does not unserialize objects.
     *
     * @return array{listings: list<array<string, mixed>>, totalPages: int, totalResults: int}
     */
    public function toArray(): array
    {
        return [
            'listings' => array_map(fn (MovieListing $listing): array => get_object_vars($listing), $this->listings),
            'totalPages' => $this->totalPages,
            'totalResults' => $this->totalResults,
        ];
    }

    /** The results from their cached form, or null when that form no longer fits. */
    public static function fromArray(mixed $data): ?self
    {
        if (! is_array($data) || ! is_array($data['listings'] ?? null) || ! is_int($data['totalPages'] ?? null) || ! is_int($data['totalResults'] ?? null)) {
            return null;
        }

        try {
            $listings = array_map(fn (mixed $listing): MovieListing => new MovieListing(...$listing), array_values($data['listings']));
        } catch (Error) {
            return null;
        }

        return new self($listings, $data['totalPages'], $data['totalResults']);
    }
}
