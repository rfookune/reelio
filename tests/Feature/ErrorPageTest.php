<?php

use App\Http\Exceptions\ErrorPageException;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

class TestErrorPage extends ErrorPageException {}

test('custom error pages expose their exception class and optional data without overriding page metadata', function (?array $data) {
    Route::get('/test-error-page', fn () => throw new TestErrorPage(409, 'Custom error', 'Custom description', data: $data));

    $response = $this->get('/test-error-page')->assertConflict();

    $response->assertInertia(fn (Assert $page) => $page->component('Error')
        ->where('exceptionClass', TestErrorPage::class)
        ->where('status', 409)->where('title', 'Custom error')->where('message', 'Custom description'));
    if ($data === null) {
        $response->assertInertia(fn (Assert $page) => $page->missing('data'));
    } else {
        $response->assertInertia(fn (Assert $page) => $page->where('data', $data));
    }
})->with([
    'no data' => [null],
    'empty data' => [[]],
    'custom data' => [['status' => 200, 'title' => 'Payload title', 'context' => ['reason' => 'example']]],
]);

test('full 429 visits use the shared error page and preserve retry and rate-limit headers', function (array $retryHeaders, string $retryAfter) {
    Route::get('/test-rate-limited', fn () => abort(429, 'Internal limit detail', [
        ...$retryHeaders,
        'X-RateLimit-Limit' => '5',
        'X-RateLimit-Remaining' => '0',
    ]))->middleware('web');

    $response = $this->get('/test-rate-limited')->assertTooManyRequests()
        ->assertHeader('Retry-After', $retryAfter)
        ->assertHeader('X-RateLimit-Limit', '5')
        ->assertHeader('X-RateLimit-Remaining', '0')
        ->assertHeader('X-Robots-Tag', 'noindex');

    $response->assertInertia(fn (Assert $page) => $page->component('Error')
        ->where('status', 429)->where('title', 'Too many requests')
        ->where('message', "You're going too fast. Try again in a moment.")
        ->where('auth.user', null));
})->with([
    'supplied retry delay' => [['Retry-After' => '17'], '17'],
    'default retry delay' => [[], '60'],
]);

test('throttled Inertia visits receive the styled page with shared data and Retry-After', function () {
    $this->freezeTime();
    Route::get('/test-throttled-visit', fn () => response()->noContent())->middleware(['web', 'throttle:1,1']);
    $this->get('/test-throttled-visit')->assertNoContent();

    $this->get('/test-throttled-visit', [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request()),
    ])->assertTooManyRequests()->assertHeader('Retry-After', '60')->assertHeader('X-Inertia', 'true')
        ->assertJsonPath('component', 'Error')
        ->assertJsonPath('props.status', 429)
        ->assertJsonPath('props.auth.user', null)
        ->assertJsonPath('props.message', "You're going too fast. Try again in a moment.");
});
