<?php

namespace App\Services\Catalog\Search;

use App\Enums\TitleKind;
use App\Services\Catalog\CacheKey;
use App\Services\Catalog\CatalogContext;
use App\Services\Catalog\MisconfigurationReporter;
use App\Services\Catalog\Sources\CatalogSource;
use App\Services\Catalog\Sources\SourceUnavailable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Sleep;

class ImdbIdLookup
{
    public function __construct(private CatalogSource $source, private CacheKey $keys, private MisconfigurationReporter $misconfiguration) {}

    /**
     * The IMDb ids already remembered for the catalog ids, without asking the source; a title whose id isn't
     * remembered, or is remembered as having none, is left out.
     *
     * @param  list<int>  $tmdbIds
     * @return array<int, string> keyed by catalog id
     */
    public function cachedImdbIds(array $tmdbIds, TitleKind $kind = TitleKind::Movie): array
    {
        return array_filter($this->rememberedImdbIds($tmdbIds, $kind), is_string(...));
    }

    /**
     * The IMDb ids for the catalog ids: those remembered, and a lookup in the source for the rest, interactively
     * and within `imdb_lookup`'s limits. Each answer is remembered, a missing id too, but a failure isn't, and the
     * first failure stops the lookups, so a struggling source is left alone. A lookup waits for its turn in the
     * lookups' shared ceiling, since no visitor is waiting on it, but only within the budget; one still without a
     * turn then is skipped, with the rest.
     *
     * @param  list<int>  $tmdbIds
     * @return array<int, string> keyed by catalog id
     */
    public function lookUpImdbIds(array $tmdbIds, TitleKind $kind = TitleKind::Movie): array
    {
        $tmdbIds = array_values(array_unique($tmdbIds));
        $remembered = $this->rememberedImdbIds($tmdbIds, $kind);
        $context = new CatalogContext(config('catalog.language'));
        $generation = $this->purgeGeneration($context);
        $lookups = 0;
        $deadline = now()->addSeconds(config('catalog.imdb_lookup.budget_seconds'));

        foreach (array_diff($tmdbIds, array_keys($remembered)) as $tmdbId) {
            if ($lookups >= config('catalog.imdb_lookup.max_per_request') || now()->greaterThan($deadline) || ! $this->awaitImdbLookupTurn($context, $deadline)) {
                break;
            }

            $lookups++;

            try {
                $imdbId = $kind === TitleKind::TvShow ? $this->source->tvImdbId($tmdbId, $context) : $this->source->imdbId($tmdbId, $context);
            } catch (SourceUnavailable $exception) {
                $this->misconfiguration->reportIfMisconfigured($exception, $context);

                break;
            }

            $this->rememberImdbId($tmdbId, $imdbId, $context, $generation, $kind);
            $remembered[$tmdbId] = $imdbId;
        }

        return array_filter($remembered, is_string(...));
    }

    /**
     * What is remembered of the catalog ids' IMDb ids: a string, null for a Movie the source has none for, and no
     * entry for one not remembered.
     *
     * @param  list<int>  $tmdbIds
     * @return array<int, string|null>
     */
    private function rememberedImdbIds(array $tmdbIds, TitleKind $kind): array
    {
        $context = new CatalogContext(config('catalog.language'));
        $generation = $this->purgeGeneration($context);
        $entries = Cache::many(array_map(fn (int $tmdbId): string => $this->imdbIdKey($tmdbId, $context, $generation, $kind), $tmdbIds));
        $remembered = [];

        foreach ($tmdbIds as $tmdbId) {
            $entry = $entries[$this->imdbIdKey($tmdbId, $context, $generation, $kind)] ?? null;

            if (is_array($entry) && array_key_exists('imdbId', $entry)) {
                $remembered[$tmdbId] = is_string($entry['imdbId']) ? $entry['imdbId'] : null;
            }
        }

        return $remembered;
    }

    public function rememberImdbId(int $tmdbId, ?string $imdbId, CatalogContext $context, ?int $generation = null, TitleKind $kind = TitleKind::Movie): void
    {
        Cache::put(
            $this->imdbIdKey($tmdbId, $context, $generation ?? $this->purgeGeneration($context), $kind),
            ['imdbId' => $imdbId],
            $imdbId === null ? now()->addSeconds(config('catalog.imdb_lookup.missing_lifetime_seconds')) : now()->addDays(config('catalog.imdb_lookup.lifetime_days')),
        );
    }

    public function forgetImdbId(int $tmdbId, CatalogContext $context): void
    {
        Cache::forget($this->imdbIdKey($tmdbId, $context, $this->purgeGeneration($context)));
    }

    /**
     * Takes a place in this second's `imdb_lookup.requests_per_second`, which every visitor's lookups share, waiting
     * for the next second while it is full, until `$deadline`. The waits are counted as well, so the wait stays
     * bounded whatever the clock says.
     */
    private function awaitImdbLookupTurn(CatalogContext $context, CarbonInterface $deadline): bool
    {
        $key = $this->keys->build('imdb-lookup-throttle', [], $context);
        $maximumWaits = intdiv(config('catalog.imdb_lookup.budget_seconds') * 1000, config('catalog.imdb_lookup.wait_milliseconds'));

        for ($waits = 0; ! RateLimiter::attempt($key, config('catalog.imdb_lookup.requests_per_second'), fn (): bool => true, 1); $waits++) {
            if ($waits >= $maximumWaits || now()->greaterThan($deadline)) {
                return false;
            }

            Sleep::for(config('catalog.imdb_lookup.wait_milliseconds'))->milliseconds();
        }

        return true;
    }

    /**
     * Part of every remembered IMDb id's key, raised by `catalog:purge`: it can't list the ids remembered for Movies
     * with no row, so it makes them unreachable instead, and they expire unread. It never expires itself, since a
     * generation that reset would make an earlier purge's ids reachable again.
     */
    private function purgeGeneration(CatalogContext $context): int
    {
        return (int) Cache::get($this->keys->build('purge-generation', [], $context), 0);
    }

    /** A Movie's key is as it always was; a TV Show's names its kind, since the two share catalog ids. */
    private function imdbIdKey(int $tmdbId, CatalogContext $context, int $generation, TitleKind $kind = TitleKind::Movie): string
    {
        return $this->keys->build('imdb-id', ['tmdb_id' => $tmdbId, 'generation' => $generation, ...($kind === TitleKind::TvShow ? ['kind' => $kind->value] : [])], $context);
    }
}
