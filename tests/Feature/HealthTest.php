<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

test('readiness returns an empty 200 after one database query and one cache read', function () {
    DB::enableQueryLog();
    Cache::shouldReceive('get')->once()->with('health')->andReturnNull();

    $this->get('/health')->assertOk()->assertContent('')->assertHeader('Cache-Control', 'no-store, private');

    expect(DB::getQueryLog())->toHaveCount(1);
    Http::assertNothingSent();
});

test('readiness returns an empty 503 when the database is unavailable', function () {
    $defaultConnection = config('database.default');
    config(['database.connections.unavailable' => [
        ...config('database.connections.pgsql'),
        'url' => null,
        'host' => '127.0.0.1',
        'port' => 1,
        'connect_timeout' => 1,
    ], 'database.default' => 'unavailable']);

    try {
        $this->get('/health')->assertServiceUnavailable()->assertContent('')->assertHeader('Cache-Control', 'no-store, private');
    } finally {
        config(['database.default' => $defaultConnection]);
        DB::purge('unavailable');
    }
});

test('readiness returns an empty 503 when the cache is unavailable', function () {
    Cache::shouldReceive('get')->once()->with('health')->andThrow(new RedisException('secret connection detail'));

    $this->getJson('/health')->assertServiceUnavailable()->assertContent('')->assertHeader('Cache-Control', 'no-store, private');
});

test('liveness does not query the database or read the cache', function () {
    DB::enableQueryLog();
    Cache::shouldReceive('get')->never();

    $this->get('/up')->assertOk();

    expect(DB::getQueryLog())->toBeEmpty();
    Http::assertNothingSent();
});
