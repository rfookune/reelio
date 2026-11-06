<?php

namespace App\Services\Catalog\Sources;

/**
 * One page of a Catalog source's list, in its order, and how many pages the list has.
 */
readonly class SectionPage
{
    /** @param  list<MovieListing>  $listings */
    public function __construct(
        public array $listings,
        public int $totalPages,
    ) {}
}
