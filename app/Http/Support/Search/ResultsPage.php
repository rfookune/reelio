<?php

namespace App\Http\Support\Search;

use App\Services\Catalog\CatalogResult;
use App\Services\Catalog\Search\CatalogSearch;
use App\Services\Catalog\Sources\CatalogSource;
use App\Services\Catalog\Sources\Listings\MovieListing;
use App\Services\Catalog\Sources\Listings\SearchResults;
use Inertia\ScrollMetadata;

/**
 * One page of the Titles a request lists, with how the Catalog answered and the page after it: the search by Name,
 * or Browse when there is no query, asked for at most once. A request that reads only the cache gets what the cache
 * holds and nothing when it holds nothing. A signed-in viewer's Browse leaves out the Titles they have seen, which
 * `UnseenBrowse` does by filling the page from the next source pages, so that page is named by the source page it
 * starts at and the next one is the source page after the last it read.
 */
class ResultsPage
{
    /** @var CatalogResult<SearchResults>|null */
    private ?CatalogResult $result = null;

    /** @var array{result: CatalogResult<SearchResults>, nextPage: ?int}|null */
    private ?array $unseenPage = null;

    public function __construct(
        private SearchRequest $request,
        private BrowseSelection $selection,
        private CatalogSearch $catalogSearch,
        private UnseenBrowse $unseenBrowse,
    ) {}

    /**
     * The Movie and TV Show listings in the source's order.
     *
     * @return list<MovieListing>
     */
    public function listings(): array
    {
        return $this->result()->value->listings ?? [];
    }

    /** Whether the Catalog gave the page, as opposed to being unable to. */
    public function isAvailable(): bool
    {
        return $this->result()->value !== null;
    }

    /** `fresh`, `stale` or `unavailable`. */
    public function status(): string
    {
        return $this->result()->status->value;
    }

    public function totalResults(): ?int
    {
        return $this->result()->value?->totalResults;
    }

    /**
     * The pages around this one: none past the search's last page or the source's. A later page the source can't
     * answer points at itself, so "Show more" asks for it again. Browse that hides what the viewer has seen is
     * paged by source page: the next page is the one after the last source page read, and there is no previous one.
     */
    public function scrollMetadata(): ScrollMetadata
    {
        $result = $this->result();
        $page = $this->request->page;

        if ($this->request->hidesSeen() && ! $result->isUnavailable()) {
            return new ScrollMetadata('page', null, $this->unseenPage()['nextPage'], $page);
        }

        if ($result->isUnavailable() && $page > 1) {
            return new ScrollMetadata('page', $page, $page, $page);
        }

        $lastPage = min($result->value->totalPages ?? 0, CatalogSource::LAST_SOURCE_PAGE);

        return new ScrollMetadata(
            'page',
            $page > 1 ? $page - 1 : null,
            $page < $lastPage ? $page + 1 : null,
            $page,
        );
    }

    /**
     * The search, or Browse when there is no query, asked for once; from the cache alone when the request reads only
     * the cache, and unavailable when the cache has nothing.
     *
     * @return CatalogResult<SearchResults>
     */
    private function result(): CatalogResult
    {
        if ($this->request->hidesSeen()) {
            return $this->result ??= $this->unseenPage()['result'];
        }

        if ($this->request->cacheMode->readsCacheOnly()) {
            $cached = $this->request->isSearching()
                ? $this->catalogSearch->cachedSearch($this->request->query, $this->request->page)
                : $this->catalogSearch->cachedBrowse($this->request->kind, $this->request->page, $this->selection->filters());

            return $this->result ??= $cached === null ? CatalogResult::unavailable() : CatalogResult::stale($cached);
        }

        return $this->result ??= $this->request->isSearching()
            ? $this->catalogSearch->search($this->request->query, $this->request->page)
            : $this->catalogSearch->browse($this->request->kind, $this->request->page, $this->selection->filters());
    }

    /**
     * Browse without the Titles the viewer has seen, read once: the page's source pages from its own, filled from
     * the next ones as `UnseenBrowse` does, from the cache alone when the request reads only the cache, in which case
     * the Titles seen are kept when the request is for the viewer's facts, so that every Title on screen gets its facts.
     *
     * @return array{result: CatalogResult<SearchResults>, nextPage: ?int}
     */
    private function unseenPage(): array
    {
        return $this->unseenPage ??= $this->unseenBrowse->page(
            $this->request->user,
            $this->request->kind,
            $this->request->page,
            $this->selection->filters(),
            $this->request->cacheMode->readsCacheOnly(),
            $this->request->cacheMode->keepsSeenTitles(),
        );
    }
}
