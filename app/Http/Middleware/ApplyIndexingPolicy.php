<?php

namespace App\Http\Middleware;

use App\Http\Support\PublicRoutes;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Marks every response `X-Robots-Tag: noindex` unless the indexing flag is on and the response is a successful page
 * on one of the public routes. Redirects, error pages, unknown URLs and private pages are never indexable, whatever
 * the flag says. A response that already set its own `X-Robots-Tag` keeps it.
 */
class ApplyIndexingPolicy
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $response->headers->has('X-Robots-Tag') && ! $this->isIndexable($request, $response)) {
            $response->headers->set('X-Robots-Tag', 'noindex');
        }

        return $response;
    }

    private function isIndexable(Request $request, Response $response): bool
    {
        return config('seo.indexing')
            && $response->getStatusCode() === Response::HTTP_OK
            && PublicRoutes::includes($request);
    }
}
