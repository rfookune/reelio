<?php

namespace App\Http\Exceptions;

use App\Http\Support\UserMovies;
use App\Models\Movie;
use App\Models\User;

/**
 * The Movie page of a Removed Movie: gone for good, so crawlers drop it, and showing none of its details.
 */
class MovieRemoved extends ErrorPageException
{
    public function __construct(?Movie $movie = null, ?User $user = null)
    {
        parent::__construct(
            410,
            'This movie is no longer available.',
            'Our data source, TMDB, has removed this movie, so we can\'t show its details any more.',
            ['X-Robots-Tag' => 'noindex'],
            data: $movie === null ? null : [
                'tmdbId' => $movie->tmdb_id,
                'userMovies' => fn (): array => UserMovies::for($user, [$movie]),
            ],
        );
    }
}
