<?php

namespace App\Services\Catalog\Enums;

/**
 * The Catalog source's lists of Movies that Discover's sections are built from. Case order is page order.
 */
enum SectionKind: string
{
    case Trending = 'trending';
    case Popular = 'popular';
    case TopRated = 'top_rated';

    public function label(): string
    {
        return match ($this) {
            self::Trending => 'Trending this week',
            self::Popular => 'Popular',
            self::TopRated => 'Top rated',
        };
    }

    /** Whether the section shows each Movie's rank. */
    public function isNumbered(): bool
    {
        return $this === self::Trending;
    }
}
