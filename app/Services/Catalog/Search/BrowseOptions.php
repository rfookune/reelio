<?php

namespace App\Services\Catalog\Search;

use App\Enums\TitleKind;
use App\Services\Catalog\CacheKey;
use App\Services\Catalog\CatalogContext;
use App\Services\Catalog\MisconfigurationReporter;
use App\Services\Catalog\Sources\CatalogSource;
use App\Services\Catalog\Sources\SourceUnavailable;
use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * The options Browse's filters offer, which the Catalog source lists: the genres of a kind, and the Streaming
 * services offering a kind in a country, each list cached for its own `search` setting
 * (`genres_seconds`, `streaming_services_seconds`). The genres of Movies and of TV Shows are lists of their own, which
 * share some names, as are the services of each country and kind. A list the source can't give is empty, and nothing
 * is cached for it, so Browse goes on without that filter until it can.
 */
class BrowseOptions
{
    public function __construct(private CatalogSource $source, private CacheKey $keys, private MisconfigurationReporter $misconfiguration) {}

    /**
     * The kind's genres, in the source's order, read from the source when they aren't cached.
     *
     * @return list<array{id: int, name: string}>
     */
    public function genres(TitleKind $kind): array
    {
        $context = $this->genreContext();

        return $this->read($this->genreKey($kind, $context), $context, fn (): array => $this->source->genres($context, $kind), config('catalog.search.genres_seconds'));
    }

    /**
     * The kind's genres from the cache alone, without asking the source; empty when there are none.
     *
     * @return list<array{id: int, name: string}>
     */
    public function cachedGenres(TitleKind $kind): array
    {
        return $this->cached($this->genreKey($kind, $this->genreContext()));
    }

    /**
     * The Streaming services offering the kind in the country, most prominent first, read from the source when
     * they aren't cached.
     *
     * @return list<array{id: int, name: string}>
     */
    public function streamingServices(TitleKind $kind, string $country): array
    {
        $context = $this->streamingServiceContext($country);

        return $this->read($this->streamingServiceKey($kind, $context), $context, fn (): array => $this->source->streamingServices($context, $kind), config('catalog.search.streaming_services_seconds'));
    }

    /**
     * The Streaming services offering the kind in the country from the cache alone, without asking the source;
     * empty when there are none.
     *
     * @return list<array{id: int, name: string}>
     */
    public function cachedStreamingServices(TitleKind $kind, string $country): array
    {
        return $this->cached($this->streamingServiceKey($kind, $this->streamingServiceContext($country)));
    }

    /**
     * The list under the key, from the cache when it holds one, else from the source and cached for the seconds.
     *
     * @param  Closure(): list<array{id: int, name: string}>  $fetch
     * @return list<array{id: int, name: string}>
     */
    private function read(string $key, CatalogContext $context, Closure $fetch, int $seconds): array
    {
        $cached = $this->cached($key);

        if ($cached !== []) {
            return $cached;
        }

        try {
            $options = $fetch();
        } catch (SourceUnavailable $exception) {
            $this->misconfiguration->reportIfMisconfigured($exception, $context);

            return [];
        }

        Cache::put($key, $options, now()->addSeconds($seconds));

        return $options;
    }

    /**
     * The list cached under the key; empty when there is none or it isn't well formed.
     *
     * @return list<array{id: int, name: string}>
     */
    private function cached(string $key): array
    {
        $cached = Cache::get($key);

        if (! is_array($cached)) {
            return [];
        }

        $options = [];

        foreach ($cached as $option) {
            if (! is_array($option) || ! is_int($option['id'] ?? null) || ! is_string($option['name'] ?? null)) {
                return [];
            }

            $options[] = ['id' => $option['id'], 'name' => $option['name']];
        }

        return $options;
    }

    private function genreContext(): CatalogContext
    {
        return new CatalogContext(config('catalog.language'));
    }

    private function streamingServiceContext(string $country): CatalogContext
    {
        return new CatalogContext(config('catalog.language'), $country);
    }

    private function genreKey(TitleKind $kind, CatalogContext $context): string
    {
        return $this->keys->build('browse-genres', ['kind' => $kind->value], $context);
    }

    private function streamingServiceKey(TitleKind $kind, CatalogContext $context): string
    {
        return $this->keys->build('browse-streaming-services', ['kind' => $kind->value], $context);
    }
}
