<?php

namespace App\Enums;

/**
 * How a Watchlist or Watched page orders its Movies; the value is the `sort` query parameter. Which sorts a page
 * accepts, and its default, depend on the list (see `UserMovieList`).
 */
enum MovieListSort: string
{
    case RecentlyAdded = 'recently-added';
    case OldestAdded = 'oldest-added';
    case RecentlyWatched = 'recently-watched';
    case OldestWatched = 'oldest-watched';
    case Title = 'title';
    case NewestRelease = 'newest-release';
    case OldestRelease = 'oldest-release';

    /** The newest first sorts run descending; every other sort runs ascending. */
    public function direction(): SortDirection
    {
        return match ($this) {
            self::RecentlyAdded, self::RecentlyWatched, self::NewestRelease => SortDirection::Desc,
            default => SortDirection::Asc,
        };
    }
}
