<?php

namespace App\Services\Catalog\Search;

use App\Enums\TitleKind;
use App\Services\Catalog\CacheKey;
use App\Services\Catalog\CatalogContext;
use App\Services\Catalog\CatalogResult;
use App\Services\Catalog\MisconfigurationReporter;
use App\Services\Catalog\Sources\CatalogSource;
use App\Services\Catalog\Sources\Listings\SearchResults;
use App\Services\Catalog\Sources\SourceUnavailable;
use App\Services\Ratings\Enums\RatingSourceName;
use App\Services\Ratings\RatingStore;
use App\Services\Ratings\TmdbRatingId;
use Illuminate\Support\Facades\Cache;

class CatalogSearch
{
    public function __construct(private CatalogSource $source, private CacheKey $keys, private RatingStore $ratings, private MisconfigurationReporter $misconfiguration) {}

    /**
     * One page of a title search. A page read within `search.fresh_seconds` is served from the cache; an older or
     * missing one is read from the source, and each result's TMDB score recorded. When the source fails, the cached
     * page is served as stale for up to `search.stale_seconds` after it was read; otherwise the search is
     * unavailable. The cache key holds a hash of the query, never its text.
     *
     * @return CatalogResult<SearchResults>
     */
    public function search(string $query, int $page): CatalogResult
    {
        $context = new CatalogContext(config('catalog.language'));
        $key = $this->searchKey($query, $page, $context);
        $cached = $this->cachedSearchEntry($key);

        if ($cached !== null && $cached['fetchedAt'] > now()->subSeconds(config('catalog.search.fresh_seconds'))->getTimestamp()) {
            return CatalogResult::fresh($cached['results']);
        }

        try {
            $results = $this->source->search($query, $page, $context);
        } catch (SourceUnavailable $exception) {
            $this->misconfiguration->reportIfMisconfigured($exception, $context);

            return $cached === null ? CatalogResult::unavailable() : CatalogResult::stale($cached['results']);
        }

        foreach ($results->listings as $listing) {
            $this->ratings->record(RatingSourceName::Tmdb, TmdbRatingId::for(TitleKind::Movie, $listing->tmdbId), $listing->voteAverage, $listing->voteCount);
        }

        Cache::put($key, ['fetchedAt' => now()->getTimestamp(), 'results' => $results->toArray()], now()->addSeconds(config('catalog.search.stale_seconds')));

        return CatalogResult::fresh($results);
    }

    /** A search page from the cache alone, fresh or stale, without asking the source; null when there is none. */
    public function cachedSearch(string $query, int $page): ?SearchResults
    {
        return $this->cachedSearchEntry($this->searchKey($query, $page, new CatalogContext(config('catalog.language'))))['results'] ?? null;
    }

    private function searchKey(string $query, int $page, CatalogContext $context): string
    {
        return $this->keys->build('search', ['query' => $query, 'page' => $page], $context);
    }

    /**
     * The cached search page and when it was read, while it is young enough to serve, stale or not.
     *
     * @return array{fetchedAt: int, results: SearchResults}|null
     */
    private function cachedSearchEntry(string $key): ?array
    {
        $cached = Cache::get($key);
        $fetchedAt = is_array($cached) && is_int($cached['fetchedAt'] ?? null) ? $cached['fetchedAt'] : null;

        if ($fetchedAt === null || $fetchedAt < now()->subSeconds(config('catalog.search.stale_seconds'))->getTimestamp()) {
            return null;
        }

        $results = SearchResults::fromArray($cached['results'] ?? null);

        return $results === null ? null : ['fetchedAt' => $fetchedAt, 'results' => $results];
    }
}
