<?php

use App\Http\Exceptions\ErrorPageException;
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
