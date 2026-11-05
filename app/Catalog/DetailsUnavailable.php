<?php

namespace App\Catalog;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * A page that needs the Catalog source can't be built because the source is down: a 503 that crawlers and clients retry.
 */
class DetailsUnavailable extends HttpException
{
    public function __construct()
    {
        parent::__construct(503, 'Details temporarily unavailable', headers: ['Retry-After' => '30', 'X-Robots-Tag' => 'noindex']);
    }
}
