<?php

namespace App\Http\Support\Search;

use App\Enums\TitleKind;
use App\Models\User;
use App\Services\Catalog\CatalogResult;
use App\Services\Catalog\Enums\CatalogStatus;
use App\Services\Catalog\Search\CatalogSearch;
use App\Services\Catalog\Sources\CatalogSource;
use App\Services\Catalog\Sources\Listings\BrowseFilters;
use App\Services\Catalog\Sources\Listings\MovieListing;
use App\Services\Catalog\Sources\Listings\SearchResults;

/**
 * A page of Browse without the Titles a user has seen. The source returns a fixed page, so hiding leaves it short;
 * the next source pages are read, whole, until the Titles kept reach `search.browse_fill_target`, the source runs
 * out, or `search.maximum_browse_source_pages` have been read. A page is therefore named by the source page it
 * starts at, and the one after it by the source page after the last one read, so a page never repeats or skips a
 * Title going forward. A page cut short by the cap, or by a source page that failed, still has a next page unless the
 * source ran out; after a failure it is the page that failed. Nothing names the page before one, since the pages
 * before a source page were read whole by the page that came earlier.
 *
 * Read from the cache alone it reads the pages already cached and no others. When only the viewer's facts are
 * reloaded, it also keeps every Title of every cached source page from the start, up to the cap, seen or not: the
 * Titles on screen may have been seen since, and the facts of each must still come back.
 *
 * @phpstan-type Page array{result: CatalogResult<SearchResults>, nextPage: ?int}
 */
class UnseenBrowse
{
    public function __construct(private CatalogSearch $catalogSearch, private SeenTitles $seenTitles) {}

    /**
     * The page starting at a source page: the Titles the user hasn't seen, the source's page and result counts
     * from its first page, and the source page after the last one read, null when the source has no more. With
     * `$cacheOnly` the source pages come from the cache alone, and with `$keepsSeen` too nothing is left out.
     *
     * @return Page
     */
    public function page(User $user, TitleKind $kind, int $startPage, BrowseFilters $filters, bool $cacheOnly = false, bool $keepsSeen = false): array
    {
        $target = config('catalog.search.browse_fill_target');
        $cap = config('catalog.search.maximum_browse_source_pages');
        $listings = [];
        $first = null;
        $stale = false;
        $reads = 0;
        $page = $startPage;

        while (true) {
            $result = $this->read($kind, $page, $filters, $cacheOnly);

            if ($result->isUnavailable()) {
                return $first === null
                    ? ['result' => $result, 'nextPage' => null]
                    : ['result' => $this->combined($first, $listings, $stale), 'nextPage' => $page];
            }

            $stale = $stale || $result->status !== CatalogStatus::Fresh;
            $first ??= $result->value;
            $listings = [...$listings, ...($keepsSeen ? $result->value->listings : $this->seenTitles->unseen($user, $result->value->listings))];
            $reads++;
            $hasMore = $page < min($first->totalPages, CatalogSource::LAST_SOURCE_PAGE);

            if (! $hasMore || (! $keepsSeen && count($listings) >= $target) || $reads >= $cap) {
                return ['result' => $this->combined($first, $listings, $stale), 'nextPage' => $hasMore ? $page + 1 : null];
            }

            $page++;
        }
    }

    /**
     * @param  list<MovieListing>  $listings
     * @return CatalogResult<SearchResults>
     */
    private function combined(SearchResults $first, array $listings, bool $stale): CatalogResult
    {
        $results = new SearchResults($listings, $first->totalPages, $first->totalResults);

        return $stale ? CatalogResult::stale($results) : CatalogResult::fresh($results);
    }

    /** @return CatalogResult<SearchResults> */
    private function read(TitleKind $kind, int $page, BrowseFilters $filters, bool $cacheOnly): CatalogResult
    {
        if (! $cacheOnly) {
            return $this->catalogSearch->browse($kind, $page, $filters);
        }

        $cached = $this->catalogSearch->cachedBrowse($kind, $page, $filters);

        return $cached === null ? CatalogResult::unavailable() : CatalogResult::stale($cached);
    }
}
