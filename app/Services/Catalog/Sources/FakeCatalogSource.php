<?php

namespace App\Services\Catalog\Sources;

use App\Services\Catalog\CatalogContext;
use App\Services\Catalog\Enums\SectionKind;

class FakeCatalogSource implements CatalogSource
{
    /** @var list<array{kind: SectionKind, key: string, query: SectionQuery, page: int}> */
    public array $sectionCalls = [];

    /**
     * @param  list<MovieDetails>  $movies
     * @param  array<string, list<list<MovieListing>>>  $sections  each list's pages, keyed by `SectionQuery::key()`
     * @param  list<SectionKind|string>  $failingSections  kinds, or `SectionQuery::key()`s
     */
    public function __construct(
        public array $movies = [],
        public bool $failing = false,
        public array $sections = [],
        public array $failingSections = [],
    ) {}

    public function movie(int $tmdbId, CatalogContext $context): ?MovieDetails
    {
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
}
