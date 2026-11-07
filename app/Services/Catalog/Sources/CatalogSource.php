<?php

namespace App\Services\Catalog\Sources;

use App\Services\Catalog\CatalogContext;

interface CatalogSource
{
    public function movie(int $tmdbId, CatalogContext $context): ?MovieDetails;

    /** One page of a list, in the source's order. A missing page is a failure, not an empty answer. */
    public function section(SectionQuery $query, int $page, CatalogContext $context): SectionPage;

    /** One page of a title search, in the source's order. A missing page is a failure, not an empty answer. */
    public function search(string $query, int $page, CatalogContext $context): SearchResults;

    /**
     * The source's whole list of Movie genres. An empty or malformed list is a failure, not an empty answer.
     *
     * @return list<array{id: int, name: string}>
     */
    public function genres(CatalogContext $context): array;
}
