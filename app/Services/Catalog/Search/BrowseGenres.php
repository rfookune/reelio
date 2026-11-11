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
 * The genres Browse offers for a kind: the Catalog source's list of Movie genres or of TV genres, cached for
 * `search.genres_seconds`. The two kinds have lists of their own, which share some names. A list the source
 * can't give is empty, and nothing is cached for it, so Browse goes on without a genre filter until it can.
 */
class BrowseGenres
{
    public function __construct(private CatalogSource $source, private CacheKey $keys, private MisconfigurationReporter $misconfiguration) {}

    /**
     * The kind's genres, in the source's order, read from the source when they aren't cached.
     *
     * @return list<array{id: int, name: string}>
     */
    public function for(TitleKind $kind): array
    {
        $context = new CatalogContext(config('catalog.language'));
        $key = $this->key($kind, $context);
        $cached = $this->cachedFor($kind);

        if ($cached !== []) {
            return $cached;
        }

        try {
            $genres = $this->source->genres($context, $kind);
        } catch (SourceUnavailable $exception) {
            $this->misconfiguration->reportIfMisconfigured($exception, $context);

            return [];
        }

        Cache::put($key, $genres, now()->addSeconds(config('catalog.search.genres_seconds')));

        return $genres;
    }

    /**
     * The kind's genres from the cache alone, without asking the source; empty when there are none.
     *
     * @return list<array{id: int, name: string}>
     */
    public function cachedFor(TitleKind $kind): array
    {
        $cached = Cache::get($this->key($kind, new CatalogContext(config('catalog.language'))));

        if (! is_array($cached)) {
            return [];
        }

        $genres = [];

        foreach ($cached as $genre) {
            if (! is_array($genre) || ! is_int($genre['id'] ?? null) || ! is_string($genre['name'] ?? null)) {
                return [];
            }

            $genres[] = ['id' => $genre['id'], 'name' => $genre['name']];
        }

        return $genres;
    }

    private function key(TitleKind $kind, CatalogContext $context): string
    {
        return $this->keys->build('browse-genres', ['kind' => $kind->value], $context);
    }
}
