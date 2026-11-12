<?php

namespace App\Services\Catalog\Sources;

use App\Enums\TitleKind;
use App\Services\Catalog\CatalogContext;
use App\Services\Catalog\Enums\SectionKind;
use App\Services\Catalog\Sources\Details\MovieDetails;
use App\Services\Catalog\Sources\Details\TvShowDetails;
use App\Services\Catalog\Sources\Listings\BrowseFilters;
use App\Services\Catalog\Sources\Listings\MovieListing;
use App\Services\Catalog\Sources\Listings\SearchResults;
use App\Services\Catalog\Sources\Listings\SectionPage;
use App\Services\Catalog\Sources\Listings\SectionQuery;

class FakeCatalogSource implements CatalogSource
{
    /** @var list<array{tmdbId: int, context: CatalogContext}> */
    public array $tvShowCalls = [];

    /** @var list<array{tmdbId: int, seasonNumber: int}> */
    public array $seasonCalls = [];

    /** @var list<array{tmdbId: int, context: CatalogContext}> */
    public array $movieCalls = [];

    /** @var list<array{kind: SectionKind, key: string, query: SectionQuery, page: int}> */
    public array $sectionCalls = [];

    /** @var list<array{query: string, page: int}> */
    public array $searchCalls = [];

    /** @var list<array{kind: string, page: int}> */
    public array $browseCalls = [];

    /** @var list<BrowseFilters> the filters of each Browse read, beside `browseCalls` */
    public array $browseFilters = [];

    /** @var list<CatalogContext> */
    public array $genreCalls = [];

    /** @var list<array{kind: string, region: ?string}> */
    public array $streamingServiceCalls = [];

    /** @var list<array{tmdbId: int, context: CatalogContext}> */
    public array $imdbIdCalls = [];

    /** @var list<array{tmdbId: int, context: CatalogContext}> */
    public array $tvImdbIdCalls = [];

    /**
     * @param  list<TvShowDetails>  $tvShows
     * @param  list<int>  $failingTvShows
     * @param  array<int, list<int>>  $failingSeasons  Season numbers by TV Show catalog id.
     * @param  array<int, string|null>  $tvImdbIds  each TV Show's IMDb id by catalog id; one not listed has none
     * @param  list<MovieDetails>  $movies
     * @param  array<string, list<list<MovieListing>>>  $sections  each list's pages, keyed by `SectionQuery::key()`
     * @param  list<SectionKind|string>  $failingSections  kinds, or `SectionQuery::key()`s
     * @param  array<string, list<list<MovieListing>>>  $searches  each query's result pages
     * @param  list<array{id: int, name: string}>  $genres
     * @param  list<list<MovieListing>>  $browsePages  the pages of Browse for Movies
     * @param  list<list<MovieListing>>  $tvBrowsePages  the pages of Browse for TV Shows
     * @param  list<array{id: int, name: string}>  $tvGenres
     * @param  array<string, list<array{id: int, name: string}>>  $streamingServices  the Streaming services for Movies, by country
     * @param  array<string, list<array{id: int, name: string}>>  $tvStreamingServices  the Streaming services for TV Shows, by country
     * @param  list<int>  $failingBrowsePages  Browse pages that fail, for either kind
     * @param  array<int, string|null>  $imdbIds  each Movie's IMDb id by catalog id; a Movie not listed has none
     */
    public function __construct(
        public array $movies = [],
        public bool $failing = false,
        public array $sections = [],
        public array $failingSections = [],
        public array $searches = [],
        public array $genres = [],
        public array $imdbIds = [],
        public array $tvShows = [],
        public array $failingTvShows = [],
        public array $failingSeasons = [],
        public array $tvImdbIds = [],
        public array $browsePages = [],
        public array $tvBrowsePages = [],
        public array $tvGenres = [],
        public array $streamingServices = [],
        public array $tvStreamingServices = [],
        public array $failingBrowsePages = [],
    ) {}

    public function tvShow(int $tmdbId, CatalogContext $context): ?TvShowDetails
    {
        $this->tvShowCalls[] = ['tmdbId' => $tmdbId, 'context' => $context];

        if ($this->failing || in_array($tmdbId, $this->failingTvShows, true)) {
            throw new SourceUnavailable('The Catalog source is unavailable.');
        }

        foreach ($this->tvShows as $tvShow) {
            if ($tvShow->tmdbId !== $tmdbId) {
                continue;
            }

            foreach ($tvShow->seasons as $season) {
                $this->seasonCalls[] = ['tmdbId' => $tmdbId, 'seasonNumber' => $season->number];
                if (in_array($season->number, $this->failingSeasons[$tmdbId] ?? [], true)) {
                    throw new SourceUnavailable('The Catalog source could not fetch a Season.');
                }
            }

            return $tvShow;
        }

        return null;
    }

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

    /** Browse with no pages given has one empty page; a page past the last is a failure, as TMDB's 404 is. */
    public function browse(TitleKind $kind, int $page, CatalogContext $context, BrowseFilters $filters = new BrowseFilters): SearchResults
    {
        $this->browseCalls[] = ['kind' => $kind->value, 'page' => $page];
        $this->browseFilters[] = $filters;

        if ($this->failing || in_array($page, $this->failingBrowsePages, true)) {
            throw new SourceUnavailable('The Catalog source is unavailable.');
        }

        $pages = ($kind === TitleKind::TvShow ? $this->tvBrowsePages : $this->browsePages) ?: [[]];

        if (! isset($pages[$page - 1])) {
            throw new SourceUnavailable('The Catalog source has no such browse page.');
        }

        return new SearchResults($pages[$page - 1], count($pages), array_sum(array_map(count(...), $pages)));
    }

    public function imdbId(int $tmdbId, CatalogContext $context): ?string
    {
        $this->imdbIdCalls[] = ['tmdbId' => $tmdbId, 'context' => $context];

        if ($this->failing) {
            throw new SourceUnavailable('The Catalog source is unavailable.');
        }

        return $this->imdbIds[$tmdbId] ?? null;
    }

    public function tvImdbId(int $tmdbId, CatalogContext $context): ?string
    {
        $this->tvImdbIdCalls[] = ['tmdbId' => $tmdbId, 'context' => $context];

        if ($this->failing) {
            throw new SourceUnavailable('The Catalog source is unavailable.');
        }

        return $this->tvImdbIds[$tmdbId] ?? null;
    }

    /** @return list<array{id: int, name: string}> */
    public function genres(CatalogContext $context, TitleKind $kind = TitleKind::Movie): array
    {
        $this->genreCalls[] = $context;
        $genres = $kind === TitleKind::TvShow ? $this->tvGenres : $this->genres;

        if ($this->failing || $genres === []) {
            throw new SourceUnavailable('The Catalog source is unavailable.');
        }

        return $genres;
    }

    /** A country with no services given has none, which is a failure, as an empty list from the source is. */
    public function streamingServices(CatalogContext $context, TitleKind $kind): array
    {
        $this->streamingServiceCalls[] = ['kind' => $kind->value, 'region' => $context->region];
        $services = ($kind === TitleKind::TvShow ? $this->tvStreamingServices : $this->streamingServices)[$context->region] ?? [];

        if ($this->failing || $services === []) {
            throw new SourceUnavailable('The Catalog source is unavailable.');
        }

        return $services;
    }
}
