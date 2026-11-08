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

    public function defaultSort(): MovieListSort
    {
        return $this === self::Watchlist ? MovieListSort::RecentlyAdded : MovieListSort::RecentlyWatched;
    }

    /** @return list<MovieListSort> */
    public function sorts(): array
    {
        return [
            $this->defaultSort(),
            $this === self::Watchlist ? MovieListSort::OldestAdded : MovieListSort::OldestWatched,
            MovieListSort::Title,
            MovieListSort::NewestRelease,
            MovieListSort::OldestRelease,
        ];
    }
}
