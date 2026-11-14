<?php

namespace App\Http\Support\Search;

/**
 * How a Search request reads the Catalog. A request is `Live` and asks the source for what the cache doesn't have.
 * The other two read the cache alone, so they search nothing new and are not limited as a search is: `CachedPage`
 * for the lookup of IMDb ratings, which needs the same Titles the page showed, and `CachedFacts` for the reload of
 * only the viewer's facts, which keeps the Titles they have seen so that every card on screen still gets its facts.
 */
enum CacheMode
{
    case Live;
    case CachedPage;
    case CachedFacts;

    public function readsCacheOnly(): bool
    {
        return $this !== self::Live;
    }

    public function keepsSeenTitles(): bool
    {
        return $this === self::CachedFacts;
    }
}
