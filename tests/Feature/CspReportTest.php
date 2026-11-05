<?php

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Log;
use Illuminate\Testing\TestResponse;

function cspReportPayload(array $overrides = []): string
{
    return json_encode(['csp-report' => array_merge([
        'document-uri' => 'https://reelio.test/movies/1?token=secret',
        'referrer' => 'https://example.com/search?q=private#frag',
        'blocked-uri' => 'https://evil.example/x.js?session=abc',
        'violated-directive' => 'script-src-elem',
        'effective-directive' => 'script-src-elem',
        'original-policy' => "default-src 'self'",
        'disposition' => 'enforce',
        'status-code' => 200,
        'source-file' => 'https://reelio.test/app.js',
        'line-number' => 12,
        'column-number' => 4,
        'script-sample' => 'alert(1)',
    ], $overrides)]);
}

function postCspReport(mixed $test, string $body): TestResponse
{
    return $test->call('POST', '/csp-report', [], [], [], [
        'CONTENT_TYPE' => 'application/csp-report',
        'REMOTE_ADDR' => '203.0.113.7',
    ], $body);
}

test('a report needs no CSRF token or sign-in and returns 204', function () {
    Log::spy();

    postCspReport($this, cspReportPayload())->assertNoContent();
});

test('the report route is exempt from CSRF verification and requires no sign-in', function () {
    $route = app('router')->getRoutes()->getByName('csp-report');

    expect(app('router')->gatherRouteMiddleware($route))
        ->not->toContain(PreventRequestForgery::class)
        ->not->toContain('auth');
});

test('a report logs a warning with only the allowed, redacted fields and no IP', function () {
    Log::spy();

    postCspReport($this, cspReportPayload())->assertNoContent();

    Log::shouldHaveReceived('warning')->once()->withArgs(function (string $message, array $context) {
        expect($context)->toBe([
            'effective-directive' => 'script-src-elem',
            'violated-directive' => 'script-src-elem',
            'document-uri' => 'https://reelio.test/movies/1',
            'referrer' => 'https://example.com/search',
            'blocked-uri' => 'https://evil.example/x.js',
            'source-file' => 'https://reelio.test/app.js',
            'line-number' => 12,
            'column-number' => 4,
        ]);
        expect(json_encode($context))->not->toContain('203.0.113.7');

        return true;
    });
});

test('malformed reports still return 204 and log nothing', function (string $body) {
    Log::spy();

    postCspReport($this, $body)->assertNoContent();

    Log::shouldNotHaveReceived('warning');
})->with(['', 'not json', '[]', '{"csp-report":"x"}']);

test('reports are limited to 10 a minute per IP and still answer 204', function () {
    Log::spy();

    foreach (range(1, 12) as $attempt) {
        postCspReport($this, cspReportPayload())->assertNoContent();
    }

    Log::shouldHaveReceived('warning')->times(10);
});
