<?php

namespace App\Http\Middleware;

use App\Http\Support\Visitor\IntendedUrl;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Records each page a guest loads, whether in full or by an Inertia visit, so sign-in can return them to it.
 * Prefetches, failed or redirected requests, non-page responses and the sign-in pages themselves are not pages
 * the guest was on.
 */
class RecordGuestPage
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->user() === null && $this->isPageLoad($request, $response)) {
            IntendedUrl::recordPage($request);
        }

        return $response;
    }

    private function isPageLoad(Request $request, Response $response): bool
    {
        return $request->isMethod('GET')
            && ! $request->prefetch()
            && $response->getStatusCode() === Response::HTTP_OK
            && ($response->headers->has('X-Inertia') || str_starts_with((string) $response->headers->get('Content-Type'), 'text/html'))
            && ! $this->isGuestOnly($request);
    }

    /** Whether the route is for guests only, as the sign-in, registration and password reset pages are. */
    private function isGuestOnly(Request $request): bool
    {
        return collect($request->route()?->gatherMiddleware() ?? [])
            ->contains(fn (mixed $middleware): bool => $middleware === 'guest' || (is_string($middleware) && str_starts_with($middleware, 'guest:')));
    }
}
