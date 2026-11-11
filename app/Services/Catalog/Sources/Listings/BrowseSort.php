<?php

namespace App\Services\Catalog\Sources\Listings;

/**
 * The orders Browse offers. `defaultFor()` is the one place that decides the order a request gets when it names
 * none, so an exception for a filter, such as a Release stage that keeps Popular, is added there.
 */
enum BrowseSort: string
{
    case TopRated = 'top_rated';
    case Popular = 'popular';

    /** The order for filters that name none: Popular while nothing narrows Browse, Top rated once something does. */
    public static function defaultFor(BrowseFilters $filters): self
    {
        return $filters->isNarrowed() ? self::TopRated : self::Popular;
    }

    /** The sort a request's `sort` value names, null when it isn't one of the offered values. */
    public static function fromRequestValue(mixed $value): ?self
    {
        return is_string($value) ? self::tryFrom($value) : null;
    }
}
