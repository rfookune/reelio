<?php

namespace App\Http\Exceptions;

/**
 * A page that needs the Catalog source can't be built because the source is down: a 503 that crawlers and clients retry.
 */
class DetailsUnavailable extends ErrorPageException
{
    public function __construct()
    {
        parent::__construct(
            503,
            'Details temporarily unavailable',
            'We can\'t reach our movie data source right now. Please try again in a moment.',
            ['Retry-After' => '30', 'X-Robots-Tag' => 'noindex'],
        );
    }
}
