<?php

namespace App\Http\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * The Movie page of a Removed Movie: gone for good, so crawlers drop it, and showing none of its details.
 */
class MovieRemoved extends HttpException
{
    public function __construct()
    {
        parent::__construct(410, 'This movie is no longer available.', headers: ['X-Robots-Tag' => 'noindex']);
    }
}
