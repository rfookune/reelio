<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    private const YOUTUBE = 'https://www.youtube-nocookie.com';

    /**
     * Set the security headers and the nonce-based CSP on every response.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = Str::random(40);

        Vite::useCspNonce($nonce);

        $response = $next($request);

        $response->headers->set('Content-Security-Policy', $this->contentSecurityPolicy($nonce));
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', $this->permissionsPolicy());
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');

        return $response;
    }

    private function contentSecurityPolicy(string $nonce): string
    {
        $devServer = $this->devServerSources();

        $directives = [
            "default-src 'self'",
            implode(' ', ["script-src 'self'", "'nonce-{$nonce}'", ...$devServer['http']]),
            implode(' ', ["style-src 'self' 'unsafe-inline'", ...$devServer['http']]),
            implode(' ', ["img-src 'self' data: https://image.tmdb.org", ...$devServer['http']]),
            implode(' ', ["font-src 'self'", ...$devServer['http']]),
            implode(' ', array_filter([
                "connect-src 'self'",
                config('services.sentry.ingest_host'),
                ...$devServer['http'],
                ...$devServer['websocket'],
            ])),
            'frame-src '.self::YOUTUBE,
            "frame-ancestors 'none'",
            "form-action 'self'",
            "base-uri 'none'",
            "object-src 'none'",
            'report-uri '.route('csp-report', absolute: false),
        ];

        return implode('; ', $directives);
    }

    private function permissionsPolicy(): string
    {
        $delegated = '(self "'.self::YOUTUBE.'")';

        return implode(', ', [
            'camera=()',
            'microphone=()',
            'geolocation=()',
            'payment=()',
            'usb=()',
            "autoplay={$delegated}",
            "fullscreen={$delegated}",
            "encrypted-media={$delegated}",
        ]);
    }

    /**
     * The Vite dev server's origin and websocket, only while it runs.
     *
     * @return array{http: list<string>, websocket: list<string>}
     */
    private function devServerSources(): array
    {
        if (! Vite::isRunningHot()) {
            return ['http' => [], 'websocket' => []];
        }

        $origin = rtrim(trim((string) file_get_contents(Vite::hotFile())), '/');
        $websocket = preg_replace('/^http/', 'ws', $origin);

        return ['http' => [$origin], 'websocket' => [$websocket]];
    }
}
