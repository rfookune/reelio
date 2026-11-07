<?php

namespace App\Services\Catalog;

use App\Models\Genre;
use App\Models\Movie;
use App\Services\Catalog\Enums\CatalogProfile;
use App\Services\Catalog\Enums\SectionKind;
use App\Services\Catalog\Exceptions\CreationLimited;
use App\Services\Catalog\Sources\CatalogSource;
use App\Services\Catalog\Sources\MovieDetails;
use App\Services\Catalog\Sources\SearchResults;
use App\Services\Catalog\Sources\SourceUnavailable;
use App\Services\Ratings\Enums\RatingSourceName;
use App\Services\Ratings\RatingStore;
use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class Catalog
{
    /** The source serves no list or search page past this one. */
    public const LAST_SOURCE_PAGE = 500;

    public function __construct(
        private CatalogSource $source,
        private CacheKey $keys,
        private RatingStore $ratings,
        private AvailabilityWriter $availability,
        private DiscoverBuilder $discover,
        private MisconfigurationReporter $misconfiguration,
    ) {}

    /** @return CatalogResult<Movie> */
    public function findOrCreate(int $tmdbId, string $actor): CatalogResult
    {
        $existing = $this->findByCatalogId($tmdbId);

        if ($existing !== null) {
            return CatalogResult::fresh($existing);
        }

        $context = new CatalogContext(config('catalog.language'));
        $this->limitCreation($actor, $context);

        try {
            $details = $this->source->movie($tmdbId, $context);
        } catch (SourceUnavailable $exception) {
            $this->misconfiguration->reportIfMisconfigured($exception, $context);

            return CatalogResult::unavailable();
        }

        if ($details === null) {
            return CatalogResult::fresh(null);
        }

        $movie = DB::transaction(function () use ($details): Movie {
            $movie = Movie::query()->firstOrCreate(['tmdb_id' => $details->tmdbId], [...$this->sourceAttributes($details), 'last_viewed_at' => now()]);

            if ($movie->wasRecentlyCreated) {
                $this->storeRelations($movie, $details);
            }

            return $movie;
        });

        $this->storeDetails($this->detailsKey($tmdbId, $context), $details);
        $this->ratings->record(RatingSourceName::Tmdb, (string) $details->tmdbId, $details->voteAverage, $details->voteCount);

        return CatalogResult::fresh($movie);
    }

    /**
     * What Discover shows, the same for every visitor. It is read, never built, while it has something to show;
     * otherwise this request builds it, asking only for the hero's details, while other visitors wait for that
     * build up to a limit.
     */
    public function discover(): Discover
    {
        $context = new CatalogContext(config('catalog.language'));
        $discover = $this->discover->stored($context);

        if (! $discover->isEmpty()) {
            return $discover;
        }

        try {
            Cache::lock($this->discover->key($context).':build', 60)->block(config('catalog.discover.build_wait_seconds'), function () use ($context): void {
                if ($this->discover->stored($context)->isEmpty()) {
                    $this->discover->build(CatalogProfile::Interactive);
                }
            });
        } catch (LockTimeoutException) {
        }

        return $this->discover->stored($context);
    }

    /**
     * One page of a title search. A page read within `search.fresh_seconds` is served from the cache; an older or
     * missing one is read from the source, and each result's TMDB score recorded. When the source fails, the cached
     * page is served as stale for up to `search.stale_seconds` after it was read; otherwise the search is
     * unavailable. The cache key holds a hash of the query, never its text.
     *
     * @return CatalogResult<SearchResults>
     */
    public function search(string $query, int $page): CatalogResult
    {
        $context = new CatalogContext(config('catalog.language'));
        $key = $this->searchKey($query, $page, $context);
        $cached = $this->cachedSearchEntry($key);

        if ($cached !== null && $cached['fetchedAt'] > now()->subSeconds(config('catalog.search.fresh_seconds'))->getTimestamp()) {
            return CatalogResult::fresh($cached['results']);
        }

        try {
            $results = $this->source->search($query, $page, $context);
        } catch (SourceUnavailable $exception) {
            $this->misconfiguration->reportIfMisconfigured($exception, $context);

            return $cached === null ? CatalogResult::unavailable() : CatalogResult::stale($cached['results']);
        }

        foreach ($results->listings as $listing) {
            $this->ratings->record(RatingSourceName::Tmdb, (string) $listing->tmdbId, $listing->voteAverage, $listing->voteCount);
        }

        Cache::put($key, ['fetchedAt' => now()->getTimestamp(), 'results' => $results->toArray()], now()->addSeconds(config('catalog.search.stale_seconds')));

        return CatalogResult::fresh($results);
    }

    /** A search page from the cache alone, fresh or stale, without asking the source; null when there is none. */
    public function cachedSearch(string $query, int $page): ?SearchResults
    {
        return $this->cachedSearchEntry($this->searchKey($query, $page, new CatalogContext(config('catalog.language'))))['results'] ?? null;
    }

    /**
     * Discover's stored Trending Movies, in rank order, without building the payload or asking the source: none
     * when nothing fresh enough is stored.
     *
     * @return list<DiscoverMovie>
     */
    public function storedTrending(): array
    {
        $trending = array_find($this->discover->stored(new CatalogContext(config('catalog.language')))->sections, fn (DiscoverSection $section): bool => $section->kind === SectionKind::Trending);

        return $trending->movies ?? [];
    }

    /** Rebuilds the Discover payload; see `DiscoverBuilder::build()`. */
    public function buildDiscover(CatalogProfile $profile): void
    {
        $this->discover->build($profile);
    }

    /**
     * Brings the stored genres up to date with the source's list: renamed ones get their new name and new ones are
     * added. A genre the source no longer lists is kept with its Movie links; each Movie's refresh replaces those.
     * When the source fails, the stored genres stay as they are.
     */
    public function refreshGenres(CatalogProfile $profile): void
    {
        $context = new CatalogContext(config('catalog.language'), profile: $profile);

        try {
            $genres = $this->source->genres($context);
        } catch (SourceUnavailable $exception) {
            $this->misconfiguration->reportIfMisconfigured($exception, $context);

            return;
        }

        Genre::query()->upsert($genres, ['id'], ['name']);
    }

    /** The stored Movie for a catalog id, without asking the source. */
    public function findByCatalogId(int $tmdbId): ?Movie
    {
        return Movie::query()->where('tmdb_id', $tmdbId)->first();
    }

    public function find(int $id): Movie
    {
        return Movie::query()->findOrFail($id);
    }

    /**
     * The Movie's Catalog source details for its page. A Movie last synced within the refresh age is served from
     * the detail cache; one synced longer ago, or with nothing cached, is refreshed in the request. When that
     * refresh fails, the cached entry is served as stale, or the result is unavailable with nothing cached. A 404
     * makes it a Removed Movie, and a Removed Movie is never fetched again.
     *
     * @return CatalogResult<MovieDetails>
     */
    public function details(Movie $movie): CatalogResult
    {
        $context = new CatalogContext(config('catalog.language'));
        $cached = $this->cachedDetails($this->detailsKey($movie->tmdb_id, $context));

        if ($cached !== null && ! $this->isDueOnView($movie)) {
            return CatalogResult::fresh($cached);
        }

        $result = $this->refresh($movie, $context);

        if ($result->isUnavailable() && $cached !== null) {
            return CatalogResult::stale($cached);
        }

        return $result;
    }

    /**
     * One refresh of a stored Movie from a single Catalog source call: its row, genres, Availability, the Streaming
     * services that references, the detail cache and its TMDB rating, with `synced_at` moved to now. A 404 makes it
     * a Removed Movie instead. A Removed Movie stays removed: it isn't fetched again. The writes hold the Movie's
     * row lock, so a refresh and a removal of the same Movie never interleave.
     *
     * @return CatalogResult<MovieDetails>
     */
    public function refresh(Movie $movie, CatalogContext $context): CatalogResult
    {
        if ($movie->isRemoved()) {
            return CatalogResult::removed();
        }

        try {
            $details = $this->source->movie($movie->tmdb_id, $context);
        } catch (SourceUnavailable $exception) {
            $this->misconfiguration->reportIfMisconfigured($exception, $context);

            return CatalogResult::unavailable();
        }

        return DB::transaction(function () use ($movie, $details, $context): CatalogResult {
            $this->lockAndReload($movie);

            if ($movie->isRemoved()) {
                return CatalogResult::removed();
            }

            if ($details === null) {
                $this->markRemoved($movie, $context);

                return CatalogResult::removed();
            }

            $movie->update($this->sourceAttributes($details));
            $this->storeRelations($movie, $details);
            $this->ratings->record(RatingSourceName::Tmdb, (string) $movie->tmdb_id, $details->voteAverage, $details->voteCount);
            $this->storeDetails($this->detailsKey($movie->tmdb_id, $context), $details);

            return CatalogResult::fresh($details);
        });
    }

    /**
     * Whether the Movie's last sync is older than a page view allows. After a view, a Movie still past this age is
     * one whose refresh failed, so what's stored may be out of date.
     */
    public function isDueOnView(Movie $movie): bool
    {
        return $movie->synced_at === null || $movie->synced_at->lessThan(now()->subSeconds(config('catalog.refresh.on_view_after_seconds')));
    }

    /**
     * The ids of up to `$limit` Movies due a scheduled refresh, in id order after `$afterId`, so the planner can
     * page through them while refreshes drop Movies out of the set.
     *
     * @return list<int>
     */
    public function dueForRefresh(int $afterId, int $limit): array
    {
        return $this->movieIds($this->heldMoviesPastSchedule(0)->where('id', '>', $afterId)->orderBy('id')->limit($limit));
    }

    /**
     * How many Movies someone holds are more than `refresh.overdue_alert_after_days` past their scheduled refresh,
     * and the ids of those synced longest ago, up to `$sampleSize`.
     *
     * @return array{count: int, sample: list<int>}
     */
    public function overdueForRefresh(int $sampleSize): array
    {
        $overdue = fn (): Builder => $this->heldMoviesPastSchedule(config('catalog.refresh.overdue_alert_after_days'));

        return [
            'count' => $overdue()->count(),
            'sample' => $this->movieIds($overdue()->orderByRaw('synced_at asc nulls first')->orderBy('id')->limit($sampleSize)),
        ];
    }

    public function recordView(Movie $movie): void
    {
        $movie->update(['last_viewed_at' => now()]);
    }

    /**
     * Movies someone holds whose last sync is older than their schedule allows, plus `$graceDays`: on any Watchlist,
     * `refresh.watchlist_after_days`; only Watched, `refresh.watched_after_days`. A Movie with no sync at all, as
     * one whose details were cleared, is always due. A Removed Movie never is: it isn't fetched again.
     *
     * @return Builder<Movie>
     */
    private function heldMoviesPastSchedule(int $graceDays): Builder
    {
        $syncedOver = fn (int $days): Closure => fn (Builder $query): Builder => $query
            ->whereNull('synced_at')
            ->orWhere('synced_at', '<', now()->subDays($days + $graceDays));
        $onAWatchlist = fn (Builder $facts): Builder => $facts->whereNotNull('watchlisted_at');

        return Movie::query()
            ->whereNull('removed_at')
            ->where(fn (Builder $query): Builder => $query
                ->where(fn (Builder $watchlisted): Builder => $watchlisted
                    ->whereHas('userMovies', $onAWatchlist)
                    ->where($syncedOver(config('catalog.refresh.watchlist_after_days'))))
                ->orWhere(fn (Builder $watchedOnly): Builder => $watchedOnly
                    ->whereHas('userMovies')
                    ->whereDoesntHave('userMovies', $onAWatchlist)
                    ->where($syncedOver(config('catalog.refresh.watched_after_days')))));
    }

    /**
     * @param  Builder<Movie>  $query
     * @return list<int>
     */
    private function movieIds(Builder $query): array
    {
        return array_values($query->get(['id'])->map(fn (Movie $movie): int => $movie->id)->all());
    }

    /**
     * Takes the Movie's row lock for the rest of the transaction and reloads the model from the locked row, so the
     * writes that follow start from what's stored now, not from a copy another writer has since changed.
     */
    private function lockAndReload(Movie $movie): void
    {
        $movie->setRawAttributes(Movie::query()->whereKey($movie->id)->lockForUpdate()->firstOrFail()->getAttributes(), sync: true);
    }

    /**
     * Keeps the row, so users' facts about the Movie survive, and clears everything taken from the Catalog source:
     * the TMDB-derived columns, genre links, Availability, the detail cache and the TMDB rating. Runs inside the
     * refresh's transaction, under the Movie's row lock.
     */
    private function markRemoved(Movie $movie, CatalogContext $context): void
    {
        $movie->update([...$this->clearedSourceAttributes($movie), 'removed_at' => now()]);
        $movie->genres()->detach();
        $movie->availabilities()->delete();
        $this->ratings->forget(RatingSourceName::Tmdb, (string) $movie->tmdb_id);
        Cache::forget($this->detailsKey($movie->tmdb_id, $context));
    }

    /**
     * The Movie columns taken from the Catalog source, stamped as synced now.
     *
     * @return array<string, mixed>
     */
    private function sourceAttributes(MovieDetails $details): array
    {
        return [
            'slug' => Str::slug($details->title ?? '') ?: 'movie',
            'title' => $details->title,
            'original_title' => $details->originalTitle,
            'overview' => $details->overview,
            'tagline' => $details->tagline,
            'release_date' => $details->releaseDate,
            'runtime' => $details->runtime,
            'poster_path' => $details->posterPath,
            'backdrop_path' => $details->backdropPath,
            'original_language' => $details->originalLanguage,
            'imdb_id' => $details->imdbId,
            'synced_at' => now(),
        ];
    }

    /**
     * Every column `sourceAttributes()` writes, set to null, so a new source column is cleared on removal too.
     *
     * @return array<string, null>
     */
    private function clearedSourceAttributes(Movie $movie): array
    {
        return array_fill_keys(array_keys($this->sourceAttributes(new MovieDetails($movie->tmdb_id))), null);
    }

    private function storeRelations(Movie $movie, MovieDetails $details): void
    {
        foreach ($details->genres as $genre) {
            Genre::query()->updateOrCreate(['id' => $genre['id']], ['name' => $genre['name']]);
        }

        $movie->genres()->sync(array_column($details->genres, 'id'));
        $this->availability->replace($movie, $details->availability);
    }

    private function searchKey(string $query, int $page, CatalogContext $context): string
    {
        return $this->keys->build('search', ['query' => $query, 'page' => $page], $context);
    }

    /**
     * The cached search page and when it was read, while it is young enough to serve, stale or not.
     *
     * @return array{fetchedAt: int, results: SearchResults}|null
     */
    private function cachedSearchEntry(string $key): ?array
    {
        $cached = Cache::get($key);
        $fetchedAt = is_array($cached) && is_int($cached['fetchedAt'] ?? null) ? $cached['fetchedAt'] : null;

        if ($fetchedAt === null || $fetchedAt < now()->subSeconds(config('catalog.search.stale_seconds'))->getTimestamp()) {
            return null;
        }

        $results = SearchResults::fromArray($cached['results'] ?? null);

        return $results === null ? null : ['fetchedAt' => $fetchedAt, 'results' => $results];
    }

    private function detailsKey(int $tmdbId, CatalogContext $context): string
    {
        return $this->keys->build('movie', ['tmdb_id' => $tmdbId], $context);
    }

    private function storeDetails(string $key, MovieDetails $details): void
    {
        Cache::put($key, ['details' => $details->toArray()], now()->addDays(config('catalog.details.hard_lifetime_days')));
    }

    private function cachedDetails(string $key): ?MovieDetails
    {
        $cached = Cache::get($key);

        return is_array($cached) && is_array($cached['details'] ?? null) ? MovieDetails::fromArray($cached['details']) : null;
    }

    private function limitCreation(string $actor, CatalogContext $context): void
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
