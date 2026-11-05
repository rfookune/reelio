<?php

namespace App\Catalog;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Protects the Catalog source from a struggling or overloaded upstream: a circuit breaker and a shared throttle
 * for interactive calls. Background calls pass straight through.
 */
class GuardedCatalogSource implements CatalogSource
{
    private const FAILURE_THRESHOLD = 5;

    private const BREAKER_OPEN_SECONDS = 30;

    private const FAILURE_MEMORY_SECONDS = 86400;

    private const LIVE_REQUESTS_PER_SECOND = 15;

    public function __construct(private CatalogSource $inner, private CacheKey $keys) {}

    public function movie(int $tmdbId, CatalogContext $context): ?MovieDetails
    {
        if ($context->profile !== CatalogProfile::Interactive) {
            return $this->inner->movie($tmdbId, $context);
        }

        $openKey = $this->keys->build('breaker-open', [], $context);
        $failuresKey = $this->keys->build('breaker-failures', [], $context);

        if (Cache::has($openKey)) {
            throw new SourceUnavailable('The Catalog source is paused after repeated failures.');
        }

        $admitted = RateLimiter::attempt($this->keys->build('live-throttle', [], $context), self::LIVE_REQUESTS_PER_SECOND, fn (): bool => true, 1);

        if (! $admitted) {
            throw new SourceUnavailable('The Catalog source request rate is at its ceiling.');
        }

        try {
            $details = $this->inner->movie($tmdbId, $context);
        } catch (SourceMisconfigured $exception) {
            throw $exception;
        } catch (SourceUnavailable $exception) {
            Cache::add($failuresKey, 0, self::FAILURE_MEMORY_SECONDS);

            if (Cache::increment($failuresKey) >= self::FAILURE_THRESHOLD) {
                Cache::put($openKey, true, self::BREAKER_OPEN_SECONDS);
            }

            throw $exception;
        }

        Cache::forget($failuresKey);

        return $details;
    }
}
