<?php

namespace App\Enums;

/**
 * The two lists of Titles a user keeps, for Movies and TV Shows alike; the value is the page's route name.
 */
enum UserList: string
{
    case Watchlist = 'watchlist';
    case Watched = 'watched';

    public function surface(): Surface
    {
        return $this === self::Watchlist ? Surface::Watchlist : Surface::Watched;
    }

    /** @return 'watchlisted_at'|'watched_at' */
    public function dateColumn(): string
    {
        return $this === self::Watchlist ? 'watchlisted_at' : 'watched_at';
    }

    public function defaultSort(): ListSort
    {
        return $this === self::Watchlist ? ListSort::RecentlyAdded : ListSort::RecentlyWatched;
    }

    /** @return list<ListSort> */
    public function sorts(): array
    {
        return [
            $this->defaultSort(),
            $this === self::Watchlist ? ListSort::OldestAdded : ListSort::OldestWatched,
            ListSort::Title,
            ListSort::NewestRelease,
            ListSort::OldestRelease,
        ];
    }
}
