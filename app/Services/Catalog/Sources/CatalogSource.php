<?php

namespace App\Services\Catalog\Sources;

use App\Services\Catalog\CatalogContext;

interface CatalogSource
{
    /** The whole TV Show, or null when removed; a partial fetch is a failure. */
    public function tvShow(int $tmdbId, CatalogContext $context): ?TvShowDetails;

    public function movie(int $tmdbId, CatalogContext $context): ?MovieDetails;

    /** One page of a list, in the source's order. A missing page is a failure, not an empty answer. */
    public function section(SectionQuery $query, int $page, CatalogContext $context): SectionPage;

    /** One page of a title search, in the source's order. A missing page is a failure, not an empty answer. */
    public function search(string $query, int $page, CatalogContext $context): SearchResults;

    /**
     * The Movie's IMDb title id, which a list or a search doesn't carry; null when the source has none for it, or
     * doesn't know the Movie. A failure is an exception, not a null.
     */
    public function imdbId(int $tmdbId, CatalogContext $context): ?string;

    /**
     * The source's whole list of Movie genres. An empty or malformed list is a failure, not an empty answer.
     *
     * @return list<array{id: int, name: string}>
     */
    public function genres(CatalogContext $context): array;
}
