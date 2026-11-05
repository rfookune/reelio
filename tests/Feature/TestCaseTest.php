<?php

use Illuminate\Http\Client\StrayRequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

test('outbound HTTP requests without a fake are blocked', function () {
    Http::get('https://example.com');
})->throws(StrayRequestException::class);

test('each test starts with an empty cache', function () {
    expect(Cache::has('test-case-probe'))->toBeFalse();

    Cache::forever('test-case-probe', true);
})->repeat(2);
