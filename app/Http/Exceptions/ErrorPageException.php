<?php

namespace App\Http\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * An HTTP error the app raises itself and shows on the shared error page. The exception message is the page's
 * title, and the description is the line under it.
 */
abstract class ErrorPageException extends HttpException
{
    /** @param  array<string, string>  $headers */
    public function __construct(int $statusCode, string $title, public readonly string $description, array $headers = [])
    {
        parent::__construct($statusCode, $title, headers: $headers);
    }
}
