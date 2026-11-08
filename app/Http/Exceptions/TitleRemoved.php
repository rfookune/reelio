<?php

namespace App\Http\Exceptions;

use App\Models\Movie;
use App\Models\TvShow;
use App\Models\User;

/**
 * The page of a Removed Title: gone for good, so crawlers drop it, and showing none of its details.
 */
class TitleRemoved extends ErrorPageException
{
    public function __construct(Movie|TvShow|null $title = null, ?User $user = null)
    {
        $noun = $title instanceof TvShow ? 'TV show' : 'movie';
        parent::__construct(
            410,
            "This {$noun} is no longer available.",
            "Our data source, TMDB, has removed this {$noun}, so we can't show its details any more.",
            ['X-Robots-Tag' => 'noindex'],
            data: self::titleData($title, $user),
        );
    }
}
