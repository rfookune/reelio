<?php

namespace App\Http\Exceptions;

use App\Models\Movie;
use App\Models\TvShow;
use App\Models\User;

/**
 * A page that needs the Catalog source can't be built because the source is down: a 503 that crawlers and clients
 * retry. For a stored Movie with no details, as one cleared at TMDB's cap, it carries the viewer's facts about it,
 * so they can still tidy their lists.
 */
class DetailsUnavailable extends ErrorPageException
{
    public function __construct(Movie|TvShow|null $movie = null, ?User $user = null)
    {
        $noun = $movie instanceof TvShow ? 'TV show' : 'movie';
        parent::__construct(
            503,
            'Details temporarily unavailable',
            "We can't reach our {$noun} data source right now. Please try again in a moment.",
            ['Retry-After' => '30', 'X-Robots-Tag' => 'noindex'],
            data: self::titleData($movie, $user),
        );
    }
}
