<?php

use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Vite;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    Vite::useHotFile(sys_get_temp_dir().'/reelio-no-vite-hot-file');
});

function cspDirectives(string $policy): array
{
    $directives = [];

    foreach (explode(';', $policy) as $directive) {
        $directive = trim($directive);

        if ($directive === '') {
            continue;
        }

        [$name, $value] = array_pad(explode(' ', $directive, 2), 2, '');
        $directives[$name] = $value;
    }

    return $directives;
}

function cspNonce(string $policy): string
{
    preg_match("/'nonce-([^']+)'/", $policy, $matches);

    return $matches[1] ?? '';
}

function expectReelioSecurityHeaders(TestResponse $response): void
{
    $directives = cspDirectives($response->headers->get('Content-Security-Policy'));

    expect($directives)->toMatchArray([
        'default-src' => "'self'",
        'style-src' => "'self' 'unsafe-inline'",
        'img-src' => "'self' data: https://image.tmdb.org",
        'font-src' => "'self'",
        'frame-src' => 'https://www.youtube-nocookie.com',
        'frame-ancestors' => "'none'",
        'form-action' => "'self'",
        'base-uri' => "'none'",
        'object-src' => "'none'",
        'report-uri' => '/csp-report',
    ]);
    expect($directives['script-src'])->toMatch("/^'self' 'nonce-[A-Za-z0-9+\/=_-]{16,}'$/");
    expect($directives['connect-src'])->toStartWith("'self'");

    $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    $response->assertHeader('Cross-Origin-Opener-Policy', 'same-origin');
    $response->assertHeaderMissing('Cross-Origin-Embedder-Policy');
    $response->assertHeader(
        'Permissions-Policy',
        'camera=(), microphone=(), geolocation=(), payment=(), usb=(), '
        .'autoplay=(self "https://www.youtube-nocookie.com"), '
        .'fullscreen=(self "https://www.youtube-nocookie.com"), '
        .'encrypted-media=(self "https://www.youtube-nocookie.com")',
    );
}

test('a public page carries the security headers', function () {
    expectReelioSecurityHeaders($this->get(route('home'))->assertOk());
});

test('a private page carries the security headers', function () {
    $response = $this->actingAs(User::factory()->create())
        ->get(route('profile.edit'))
        ->assertOk();

    expectReelioSecurityHeaders($response);
});

test('an error page carries the security headers', function () {
    expectReelioSecurityHeaders($this->get('/no-such-page')->assertNotFound());
});

test('the nonce is fresh per request and applied to the page scripts', function () {
    $first = $this->get(route('home'))->assertOk();
    $second = $this->get(route('home'))->assertOk();

    $firstNonce = cspNonce($first->headers->get('Content-Security-Policy'));
    $secondNonce = cspNonce($second->headers->get('Content-Security-Policy'));

    expect($firstNonce)->not->toBe('')->not->toBe($secondNonce);

    $html = $first->getContent();

    preg_match_all('/<script\b[^>]*>/', $html, $scripts);
    $executable = array_filter(
        $scripts[0],
        fn (string $tag) => ! str_contains($tag, 'application/json'),
    );

    expect($executable)->not->toBeEmpty();

    foreach ($executable as $tag) {
        expect($tag)->toContain('nonce="'.$firstNonce.'"');
    }
});

test('the Vite dev server origin and websocket are allowed while it runs', function () {
    $hotFile = tempnam(sys_get_temp_dir(), 'hot');
    File::put($hotFile, 'http://localhost:5173');
    Vite::useHotFile($hotFile);

    try {
        $directives = cspDirectives(
            $this->get(route('home'))->headers->get('Content-Security-Policy'),
        );
    } finally {
        File::delete($hotFile);
    }

    expect($directives['script-src'])->toContain('http://localhost:5173');
    expect($directives['connect-src'])->toContain('http://localhost:5173')->toContain('ws://localhost:5173');
});

test('the dev server is not allowed when it is not running', function () {
    $policy = $this->get(route('home'))->headers->get('Content-Security-Policy');

    expect($policy)->not->toContain('localhost:5173')->not->toContain('ws://');
});

test('the session cookie is secure, http-only and same-site lax in production', function () {
    $previous = $_SERVER['APP_ENV'] ?? null;
    $_SERVER['APP_ENV'] = 'production';

    try {
        $session = require config_path('session.php');
    } finally {
        $_SERVER['APP_ENV'] = $previous ?? 'testing';
    }

    expect($session['secure'])->toBeTrue()
        ->and($session['http_only'])->toBeTrue()
        ->and($session['same_site'])->toBe('lax');
});
