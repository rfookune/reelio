<?php

namespace App\Http\Exceptions;

use App\Models\Movie;
use App\Models\TvShow;
use App\Models\User;

/**
 * The Movie page of a Removed Movie: gone for good, so crawlers drop it, and showing none of its details.
 */
class MovieRemoved extends ErrorPageException
{
    public function __construct(Movie|TvShow|null $movie = null, ?User $user = null)
    {
        $noun = $movie instanceof TvShow ? 'TV show' : 'movie';
        parent::__construct(
            410,
            "This {$noun} is no longer available.",
            "Our data source, TMDB, has removed this {$noun}, so we can't show its details any more.",
            ['X-Robots-Tag' => 'noindex'],
            data: self::titleData($movie, $user),
        );
    }
}
