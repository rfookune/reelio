<?php

namespace App\Services\Catalog\Sources;

use App\Enums\TitleKind;
use App\Services\Catalog\CacheKey;
use App\Services\Catalog\CatalogContext;
use App\Services\Catalog\Enums\CatalogProfile;
use App\Services\Catalog\Sources\Details\MovieDetails;
use App\Services\Catalog\Sources\Details\TvShowDetails;
use App\Services\Catalog\Sources\Listings\BrowseFilters;
use App\Services\Catalog\Sources\Listings\SearchResults;
use App\Services\Catalog\Sources\Listings\SectionPage;
use App\Services\Catalog\Sources\Listings\SectionQuery;
use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Protects the Catalog source from a struggling or overloaded upstream: a circuit breaker and a shared throttle
 * for interactive calls. Background calls pass straight through; their throttle counts each request the source
 * sends, retries included, so it is applied there (`BackgroundThrottle`).
 */
class GuardedCatalogSource implements CatalogSource
{
    public function __construct(private CatalogSource $inner, private CacheKey $keys) {}

    /** The whole TV Show, or null when removed; a partial fetch is a failure. */
    public function tvShow(int $tmdbId, CatalogContext $context): ?TvShowDetails
    {
        return $this->guard($context, fn (): ?TvShowDetails => $this->inner->tvShow($tmdbId, $context));
    }

    public function movie(int $tmdbId, CatalogContext $context): ?MovieDetails
    {
        return $this->guard($context, fn (): ?MovieDetails => $this->inner->movie($tmdbId, $context));
    }

    public function section(SectionQuery $query, int $page, CatalogContext $context): SectionPage
    {
        return $this->guard($context, fn (): SectionPage => $this->inner->section($query, $page, $context));
    }

    public function search(string $query, int $page, CatalogContext $context): SearchResults
    {
        return $this->guard($context, fn (): SearchResults => $this->inner->search($query, $page, $context));
    }

    public function browse(TitleKind $kind, int $page, CatalogContext $context, BrowseFilters $filters = new BrowseFilters): SearchResults
    {
        return $this->guard($context, fn (): SearchResults => $this->inner->browse($kind, $page, $context, $filters));
    }

    public function imdbId(int $tmdbId, CatalogContext $context): ?string
    {
        return $this->guard($context, fn (): ?string => $this->inner->imdbId($tmdbId, $context));
    }

    public function tvImdbId(int $tmdbId, CatalogContext $context): ?string
    {
        return $this->guard($context, fn (): ?string => $this->inner->tvImdbId($tmdbId, $context));
    }

    /** @return list<array{id: int, name: string}> */
    public function genres(CatalogContext $context, TitleKind $kind = TitleKind::Movie): array
    {
        return $this->guard($context, fn (): array => $this->inner->genres($context, $kind));
    }

    /**
     * Makes an interactive call through the circuit breaker and the live throttle; a background call goes
     * straight through.
     *
     * @template T
     *
     * @param  Closure(): T  $call
     * @return T
     */
    private function guard(CatalogContext $context, Closure $call): mixed
    {
        if ($context->profile !== CatalogProfile::Interactive) {
            return $call();
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
            $result = $call();
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

        return $result;
    }
}
