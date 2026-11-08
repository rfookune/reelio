<?php

namespace App\Services\Catalog\Sources\Tmdb;

use App\Services\Catalog\CacheKey;
use App\Services\Catalog\CatalogContext;
use App\Services\Catalog\Sources\SourceUnavailable;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Sleep;

/**
 * The rate every background request to the Catalog source shares, retries included, whatever its context. Over
 * it, a request waits its turn rather than failing, because no visitor is waiting on it.
 */
class BackgroundThrottle
{
    /** How long a request waits before trying for a place again. */
    private const WAIT_STEP_MILLISECONDS = 100;

    public function __construct(private CacheKey $keys) {}

    /**
     * Takes a place in this second's `guard.background_requests_per_second`, waiting for the next second while
     * it is full. A request still without a place after `guard.background_wait_seconds` fails as unavailable. The
     * wait is counted in the time slept, so it stays bounded whatever the clock says.
     */
    public function awaitTurn(): void
    {
        $key = $this->keys->build('background-throttle', [], new CatalogContext(config('catalog.language')));
        $waitedMilliseconds = 0;

        while (! RateLimiter::attempt($key, config('catalog.guard.background_requests_per_second'), fn (): bool => true, 1)) {
            if ($waitedMilliseconds >= config('catalog.guard.background_wait_seconds') * 1000) {
                throw new SourceUnavailable('The Catalog source background request rate stayed at its ceiling.');
            }

            Sleep::for(self::WAIT_STEP_MILLISECONDS)->milliseconds();
            $waitedMilliseconds += self::WAIT_STEP_MILLISECONDS;
        }
    }
}
