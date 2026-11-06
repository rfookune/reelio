<?php

namespace App\Catalog\Source;

use App\Catalog\CatalogContext;
use App\Catalog\CatalogProfile;
use App\Catalog\CacheKey;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Protects the Catalog source from a struggling or overloaded upstream: a circuit breaker and a shared throttle
 * for interactive calls. Background calls pass straight through.
 */
class GuardedCatalogSource implements CatalogSource
{
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

        $admitted = RateLimiter::attempt($this->keys->build('live-throttle', [], $context), config('catalog.guard.live_requests_per_second'), fn (): bool => true, 1);

        if (! $admitted) {
            throw new SourceUnavailable('The Catalog source request rate is at its ceiling.');
        }

        try {
            $details = $this->inner->movie($tmdbId, $context);
        } catch (SourceMisconfigured $exception) {
            throw $exception;
        } catch (SourceUnavailable $exception) {
            Cache::add($failuresKey, 0, config('catalog.guard.failure_memory_seconds'));

            if (Cache::increment($failuresKey) >= config('catalog.guard.failure_threshold')) {
                Cache::put($openKey, true, config('catalog.guard.breaker_open_seconds'));
            }

            throw $exception;
        }

        Cache::forget($failuresKey);

        return $details;
    }
}
