<?php

namespace App\Services\Catalog\Titles;

use App\Models\TvShow;
use App\Services\Catalog\CacheKey;
use App\Services\Catalog\CatalogContext;
use App\Services\Catalog\CatalogResult;
use App\Services\Catalog\MisconfigurationReporter;
use App\Services\Catalog\Sources\CatalogSource;
use App\Services\Catalog\Sources\Details\TvShowDetails;
use App\Services\Catalog\Sources\SourceUnavailable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class TvShowCatalog
{
    public function __construct(private CatalogSource $source, private CacheKey $keys, private MisconfigurationReporter $misconfiguration, private TvShowWriter $tvShows, private CreationLimiter $creation) {}

    /** @return CatalogResult<TvShow> */
    public function findOrCreateTvShow(int $tmdbId, string $actor): CatalogResult
    {
        if (! config('catalog.tv_shows.enabled')) {
            return CatalogResult::unavailable();
        }
        $existing = TvShow::query()->where('tmdb_id', $tmdbId)->first();
        if ($existing !== null) {
            return CatalogResult::fresh($existing);
        }
        $context = new CatalogContext(config('catalog.language'));
        $this->creation->limitCreation($actor, $context);
        try {
            $details = $this->source->tvShow($tmdbId, $context);
        } catch (SourceUnavailable $exception) {
            $this->misconfiguration->reportIfMisconfigured($exception, $context);

            return CatalogResult::unavailable();
        }
        if ($details === null) {
            return CatalogResult::fresh(null);
        }

        return DB::transaction(function () use ($tmdbId, $details, $context): CatalogResult {
            $tvShow = TvShow::query()->firstOrCreate(['tmdb_id' => $tmdbId], ['last_viewed_at' => now()]);
            if ($tvShow->wasRecentlyCreated) {
                $this->tvShows->store($tvShow, $details);
                $this->storeTvShowPageData($tvShow, $details, $context);
            }

            return CatalogResult::fresh($tvShow);
        });
    }

    /**
     * Refreshes the whole TV Show when older than the view interval. A failed fetch never writes a partial
     * Season inventory; stored details remain usable as stale, while a Title with no successful sync is unavailable.
     *
     * @return CatalogResult<TvShow>
     */
    public function tvShowDetails(TvShow $tvShow): CatalogResult
    {
        if (! config('catalog.tv_shows.enabled')) {
            return CatalogResult::unavailable();
        }
        if ($tvShow->isRemoved()) {
            return CatalogResult::removed();
        }
        if ($tvShow->synced_at !== null && $tvShow->synced_at->greaterThanOrEqualTo(now()->subSeconds(config('catalog.refresh.on_view_after_seconds'))) && $this->tvShowPageData($tvShow) !== null) {
            return CatalogResult::fresh($tvShow);
        }
        $context = new CatalogContext(config('catalog.language'));
        try {
            $details = $this->source->tvShow($tvShow->tmdb_id, $context);
        } catch (SourceUnavailable $exception) {
            $this->misconfiguration->reportIfMisconfigured($exception, $context);

            return $tvShow->hasSourceDetails() ? CatalogResult::stale($tvShow) : CatalogResult::unavailable();
        }

        return DB::transaction(function () use ($tvShow, $details, $context): CatalogResult {
            $locked = TvShow::query()->lockForUpdate()->findOrFail($tvShow->id);
            $tvShow->setRawAttributes($locked->getAttributes(), true);
            if ($tvShow->isRemoved()) {
                return CatalogResult::removed();
            }
            if ($details === null) {
                $this->tvShows->markRemoved($tvShow);
                Cache::forget($this->tvShowPageDataKey($tvShow, $context));

                return CatalogResult::removed();
            }
            $this->tvShows->store($tvShow, $details);
            $this->storeTvShowPageData($tvShow, $details, $context);

            return CatalogResult::fresh($tvShow);
        });
    }

    public function tvShowPageData(TvShow $tvShow): ?TvShowPageData
    {
        if ($tvShow->isRemoved() || ! $tvShow->hasSourceDetails()) {
            return null;
        }
        $data = Cache::get($this->tvShowPageDataKey($tvShow, new CatalogContext(config('catalog.language'))));

        return is_array($data) ? TvShowPageData::fromArray($data) : null;
    }

    private function tvShowPageDataKey(TvShow $tvShow, CatalogContext $context): string
    {
        return $this->keys->build('tv-page-details', ['tmdbId' => $tvShow->tmdb_id], $context);
    }

    private function storeTvShowPageData(TvShow $tvShow, TvShowDetails $details, CatalogContext $context): void
    {
        Cache::put($this->tvShowPageDataKey($tvShow, $context), TvShowPageData::fromDetails($details)->toArray(), now()->addDays(config('catalog.details.hard_lifetime_days')));
    }
}
