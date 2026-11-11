<?php

namespace App\Services\Catalog\Search;

use App\Enums\TitleKind;
use App\Services\Catalog\CacheKey;
use App\Services\Catalog\CatalogContext;
use App\Services\Catalog\MisconfigurationReporter;
use App\Services\Catalog\Sources\CatalogSource;
use App\Services\Catalog\Sources\SourceUnavailable;
use Illuminate\Support\Facades\Cache;

/**
 * The Streaming services Browse offers for a kind in a country: the Catalog source's list of services that offer
 * that kind there, most prominent first, cached for `search.streaming_services_seconds`. Each country and kind has a
 * list of its own. A list the source can't give is empty, and nothing is cached for it, so Browse goes on without
 * a Streaming service filter until it can.
 */
class BrowseStreamingServices
{
    public function __construct(private CatalogSource $source, private CacheKey $keys, private MisconfigurationReporter $misconfiguration) {}

    /**
     * The kind's services in the country, read from the source when they aren't cached.
     *
     * @return list<array{id: int, name: string}>
     */
    public function for(TitleKind $kind, string $country): array
    {
        $context = $this->context($country);
        $cached = $this->cachedFor($kind, $country);

        if ($cached !== []) {
            return $cached;
        }

        try {
            $services = $this->source->streamingServices($context, $kind);
        } catch (SourceUnavailable $exception) {
            $this->misconfiguration->reportIfMisconfigured($exception, $context);

            return [];
        }

        Cache::put($this->key($kind, $context), $services, now()->addSeconds(config('catalog.search.streaming_services_seconds')));

        return $services;
    }

    /**
     * The kind's services in the country from the cache alone, without asking the source; empty when there are none.
     *
     * @return list<array{id: int, name: string}>
     */
    public function cachedFor(TitleKind $kind, string $country): array
    {
        $cached = Cache::get($this->key($kind, $this->context($country)));

        if (! is_array($cached)) {
            return [];
        }

        $services = [];

        foreach ($cached as $service) {
            if (! is_array($service) || ! is_int($service['id'] ?? null) || ! is_string($service['name'] ?? null)) {
                return [];
            }

            $services[] = ['id' => $service['id'], 'name' => $service['name']];
        }

        return $services;
    }

    private function context(string $country): CatalogContext
    {
        return new CatalogContext(config('catalog.language'), $country);
    }

    private function key(TitleKind $kind, CatalogContext $context): string
    {
        return $this->keys->build('browse-streaming-services', ['kind' => $kind->value], $context);
    }
}
