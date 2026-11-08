<?php

namespace App\Enums;

enum UserMovieList: string
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

    public function defaultSort(): string
    {
        return $this === self::Watchlist ? 'recently-added' : 'recently-watched';
    }

    /** @return list<string> */
    public function sorts(): array
    {
        return [$this->defaultSort(), $this === self::Watchlist ? 'oldest-added' : 'oldest-watched', 'title', 'newest-release', 'oldest-release'];
    }
}
