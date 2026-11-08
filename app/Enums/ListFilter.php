<?php

namespace App\Enums;

/**
 * Which kinds of Title a Watchlist or Watched page shows; the value is the `filter` query parameter, left out for
 * `All`.
 */
enum ListFilter: string
{
    case All = 'all';
    case Movies = 'movies';
    case TvShows = 'tv-shows';

    /** The one kind the filter keeps, or null for every kind. */
    public function kind(): ?TitleKind
    {
        return match ($this) {
            self::All => null,
            self::Movies => TitleKind::Movie,
            self::TvShows => TitleKind::TvShow,
        };
    }
}
