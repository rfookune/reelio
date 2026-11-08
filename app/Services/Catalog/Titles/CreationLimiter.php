<?php

namespace App\Services\Catalog\Titles;

use App\Services\Catalog\CacheKey;
use App\Services\Catalog\CatalogContext;
use App\Services\Catalog\Exceptions\CreationLimited;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

class CreationLimiter
{
    public function __construct(private CacheKey $keys) {}

    public function limitCreation(string $actor, CatalogContext $context): void
    {
        $parameters = ['actor' => $actor];
        $key = $this->keys->build('creation', $parameters, $context);
        $acquired = Cache::lock($this->keys->build('creation-lock', $parameters, $context), 5)->get(function () use ($key): bool {
            if (RateLimiter::tooManyAttempts($key, config('catalog.creation_limit.attempts'))) {
                throw new CreationLimited(max(1, RateLimiter::availableIn($key)));
            }

            RateLimiter::hit($key, config('catalog.creation_limit.decay_seconds'));

            return true;
        });

        if (! $acquired) {
            throw new CreationLimited(1);
        }
    }
}
