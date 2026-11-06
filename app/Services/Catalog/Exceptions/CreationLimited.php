<?php

namespace App\Services\Catalog\Exceptions;

use RuntimeException;

class CreationLimited extends RuntimeException
{
    public function __construct(public readonly int $retryAfter)
    {
        parent::__construct('Try again in a moment.');
    }
}
