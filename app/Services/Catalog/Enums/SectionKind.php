<?php

namespace App\Services\Catalog\Enums;

/**
 * The Catalog source's lists of Movies that Discover's sections are built from. Case order is page order; the
 * genre collections, one per genre picked for the day, come last. `DiscoverBuilder::queries()` follows this order.
 */
enum SectionKind: string
{
    case Trending = 'trending';
    case Popular = 'popular';
    case ComingSoon = 'coming_soon';
    case TopRated = 'top_rated';
    case Genre = 'genre';

    /** The section's title. A genre collection is titled by its genre, so its kind's label is never shown. */
    public function label(): string
    {
        return match ($this) {
            self::Trending => 'Trending this week',
            self::Popular => 'Popular',
            self::ComingSoon => 'Coming soon',
            self::TopRated => 'Top rated',
            self::Genre => 'Genre',
        };
    }

    /** Whether the section shows each Movie's rank. */
    public function isNumbered(): bool
    {
        return $this === self::Trending;
    }
}
