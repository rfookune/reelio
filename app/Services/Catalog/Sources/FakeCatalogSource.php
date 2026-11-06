<?php

namespace App\Services\Catalog\Sources;

use App\Services\Catalog\CatalogContext;
use App\Services\Catalog\Enums\SectionKind;

class FakeCatalogSource implements CatalogSource
{
    /** @var list<array{kind: SectionKind, page: int}> */
    public array $sectionCalls = [];

    /**
     * @param  list<MovieDetails>  $movies
     * @param  array<string, list<list<MovieListing>>>  $sections  each kind's pages, keyed by `SectionKind` value
     * @param  list<SectionKind>  $failingSections
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

    /** A kind with no pages given has one empty page; a page past the last is a failure, as TMDB's 404 is. */
    public function section(SectionKind $kind, int $page, CatalogContext $context): SectionPage
    {
        $this->sectionCalls[] = ['kind' => $kind, 'page' => $page];

        if ($this->failing || in_array($kind, $this->failingSections, true)) {
            throw new SourceUnavailable('The Catalog source is unavailable.');
        }

        $pages = $this->sections[$kind->value] ?? [[]];

        if (! isset($pages[$page - 1])) {
            throw new SourceUnavailable('The Catalog source has no such list page.');
        }

        return new SectionPage($pages[$page - 1], count($pages));
    }
}
