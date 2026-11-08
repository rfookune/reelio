<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    /**
     * Start every test with an empty Redis cache and no network access.
     *
     * Flushing the Redis cache empties its whole database index, which is why
     * phpunit.xml pins the suite to a dedicated index.
     */
    protected function setUp(): void
    {
        parent::setUp();

        config(['catalog.tv_shows.enabled' => true]);

        Cache::flush();
        Http::preventStrayRequests();
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
