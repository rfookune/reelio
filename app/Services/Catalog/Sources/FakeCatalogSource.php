<?php

namespace App\Services\Catalog\Sources;

use App\Services\Catalog\CatalogContext;
use App\Services\Catalog\Enums\SectionKind;

class FakeCatalogSource implements CatalogSource
{
    /** @var list<array{tmdbId: int, context: CatalogContext}> */
    public array $movieCalls = [];

    /** @var list<array{kind: SectionKind, key: string, query: SectionQuery, page: int}> */
    public array $sectionCalls = [];

    /** @var list<array{query: string, page: int}> */
    public array $searchCalls = [];

    /** @var list<CatalogContext> */
    public array $genreCalls = [];

    /**
     * @param  list<MovieDetails>  $movies
     * @param  array<string, list<list<MovieListing>>>  $sections  each list's pages, keyed by `SectionQuery::key()`
     * @param  list<SectionKind|string>  $failingSections  kinds, or `SectionQuery::key()`s
     * @param  array<string, list<list<MovieListing>>>  $searches  each query's result pages
     * @param  list<array{id: int, name: string}>  $genres
     */
    public function __construct(
        public array $movies = [],
        public bool $failing = false,
        public array $sections = [],
        public array $failingSections = [],
        public array $searches = [],
        public array $genres = [],
    ) {}

    public function movie(int $tmdbId, CatalogContext $context): ?MovieDetails
    {
        $this->movieCalls[] = ['tmdbId' => $tmdbId, 'context' => $context];

        if ($this->failing) {
            throw new SourceUnavailable('The Catalog source is unavailable.');
        }

        foreach ($this->movies as $movie) {
            if ($movie->tmdbId === $tmdbId) {
                return $movie;
            }
        }

        return null;
    }

    /** A list with no pages given has one empty page; a page past the last is a failure, as TMDB's 404 is. */
    public function section(SectionQuery $query, int $page, CatalogContext $context): SectionPage
    {
        $this->sectionCalls[] = ['kind' => $query->kind, 'key' => $query->key(), 'query' => $query, 'page' => $page];

        if ($this->failing || in_array($query->kind, $this->failingSections, true) || in_array($query->key(), $this->failingSections, true)) {
            throw new SourceUnavailable('The Catalog source is unavailable.');
        }

        $pages = $this->sections[$query->key()] ?? [[]];

        if (! isset($pages[$page - 1])) {
            throw new SourceUnavailable('The Catalog source has no such list page.');
        }

        return new SectionPage($pages[$page - 1], count($pages));
    }

    /**
     * A query with no pages given has one empty page; a page past the last is a failure, as TMDB's 404 is. The
     * result count is every listing on the query's pages.
     */
    public function search(string $query, int $page, CatalogContext $context): SearchResults
    {
        $this->searchCalls[] = ['query' => $query, 'page' => $page];

        if ($this->failing) {
            throw new SourceUnavailable('The Catalog source is unavailable.');
        }

        $pages = $this->searches[$query] ?? [[]];

        if (! isset($pages[$page - 1])) {
            throw new SourceUnavailable('The Catalog source has no such search page.');
        }

        return new SearchResults($pages[$page - 1], count($pages), array_sum(array_map(count(...), $pages)));
    }

    /** @return list<array{id: int, name: string}> */
    public function genres(CatalogContext $context): array
    {
        $this->genreCalls[] = $context;

        if ($this->failing) {
            throw new SourceUnavailable('The Catalog source is unavailable.');
        }

        return $this->genres;
    }
}
