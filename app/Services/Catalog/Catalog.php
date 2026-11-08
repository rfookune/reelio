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
use App\Services\Ratings\TmdbRatingId;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Sleep;
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
        $this->rememberImdbId($details->tmdbId, $details->imdbId, $context);
        $this->ratings->record(RatingSourceName::Tmdb, TmdbRatingId::for('movie', $details->tmdbId), $details->voteAverage, $details->voteCount);

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
            $this->ratings->record(RatingSourceName::Tmdb, TmdbRatingId::for('movie', $listing->tmdbId), $listing->voteAverage, $listing->voteCount);
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

    /**
     * The IMDb ids already remembered for the catalog ids, without asking the source; a Movie whose id isn't
     * remembered, or is remembered as having none, is left out.
     *
     * @param  list<int>  $tmdbIds
     * @return array<int, string> keyed by catalog id
     */
    public function cachedImdbIds(array $tmdbIds): array
    {
        return array_filter($this->rememberedImdbIds($tmdbIds), is_string(...));
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
    public function lookUpImdbIds(array $tmdbIds): array
    {
        $tmdbIds = array_values(array_unique($tmdbIds));
        $remembered = $this->rememberedImdbIds($tmdbIds);
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
                $imdbId = $this->source->imdbId($tmdbId, $context);
            } catch (SourceUnavailable $exception) {
                $this->misconfiguration->reportIfMisconfigured($exception, $context);

                break;
            }

            $this->rememberImdbId($tmdbId, $imdbId, $context, $generation);
            $remembered[$tmdbId] = $imdbId;
        }

        return array_filter($remembered, is_string(...));
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
            $this->ratings->record(RatingSourceName::Tmdb, TmdbRatingId::for('movie', $movie->tmdb_id), $details->voteAverage, $details->voteCount);
            $this->storeDetails($this->detailsKey($movie->tmdb_id, $context), $details);
            $this->rememberImdbId($movie->tmdb_id, $details->imdbId, $context);

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

    /**
     * One run of the daily cleanup, each step taking at most `cleanup.batch_size` rows:
     *
     * - a Movie whose last successful sync is more than `cap.after_days` old has everything from the Catalog source
     *   cleared, as a Removed Movie does, but isn't marked removed: its next successful refresh restores it;
     * - TMDB ratings older than that are deleted, whether or not a Movie exists for them;
     * - Movies nobody holds and nobody has viewed for `purge_unviewed.after_days` are deleted, while that purge is
     *   switched on;
     * - Streaming services no Availability references any more are deleted.
     *
     * Returns whether any step filled its batch, so another run should follow.
     */
    public function cleanUp(): bool
    {
        $batchSize = config('catalog.cleanup.batch_size');
        $capCutoff = now()->subDays(config('catalog.cap.after_days'));
        $batchCounts = [
            $this->clearPastCap($capCutoff, $batchSize),
            $this->ratings->forgetOlderThan(RatingSourceName::Tmdb, $capCutoff, $batchSize),
            $this->purgeUnviewed($batchSize),
            $this->availability->deleteUnreferencedServices($batchSize, now()->subSeconds(config('catalog.cleanup.unreferenced_service_grace_seconds'))),
        ];

        return in_array($batchSize, $batchCounts, true);
    }

    /**
     * For when the licence to TMDB's data ends: deletes every TMDB rating, applies the cap's clearing to every Movie
     * that holds details from the Catalog source, then deletes the genres and Streaming services nothing references
     * any more, and the Discover payload. Users' facts survive. Movies are taken once each, in id order, so a refresh running
     * meanwhile can't keep it going; one that runs afterwards fetches the Movie again, so the source's token should
     * be removed first. The IMDb ids remembered for Movies with no row can't be listed, so a new purge generation
     * makes them unreachable. A refresh running meanwhile can also lose its genre links, which cascade when a genre is
     * deleted, or fail. Cached search pages aren't listed anywhere, so they are left to expire within
     * `search.stale_seconds`. Returns how many Movies were cleared and how many ratings deleted.
     *
     * @return array{movies: int, ratings: int}
     */
    public function purgeSourceData(): array
    {
        $ratings = $this->ratings->forgetSource(RatingSourceName::Tmdb);
        $withDetails = fn (Builder $query): Builder => $query->whereNotNull('synced_at');
        $cleared = 0;
        $afterId = 0;

        do {
            $movieIds = $this->movieIds($withDetails(Movie::query())->where('id', '>', $afterId)->orderBy('id')->limit(config('catalog.cleanup.batch_size')));
            $cleared += $this->clearEach($movieIds, $withDetails);
            $afterId = $movieIds === [] ? $afterId : $movieIds[array_key_last($movieIds)];
        } while ($movieIds !== []);

        Genre::query()->whereDoesntHave('titles')->delete();
        $batchSize = config('catalog.cleanup.batch_size');

        while ($this->availability->deleteUnreferencedServices($batchSize) === $batchSize) {
        }

        $context = new CatalogContext(config('catalog.language'));
        Cache::forget($this->discover->key($context));
        $generationKey = $this->keys->build('purge-generation', [], $context);
        Cache::add($generationKey, 0);
        Cache::increment($generationKey);

        return ['movies' => $cleared, 'ratings' => $ratings];
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
     * Clears up to `$limit` Movies last synced before `$cutoff`, and returns how many it considered. Each is locked
     * and checked again first, so a Movie a refresh has just restored is left alone.
     */
    private function clearPastCap(CarbonInterface $cutoff, int $limit): int
    {
        $pastCap = fn (Builder $query): Builder => $query->whereNull('removed_at')->where('synced_at', '<', $cutoff);
        $candidateIds = $this->movieIds($pastCap(Movie::query())->orderBy('id')->limit($limit));
        $this->clearEach($candidateIds, $pastCap);

        return count($candidateIds);
    }

    /**
     * Clears each of the Movies that still matches `$condition` once its row is locked, so a Movie a refresh has just
     * changed is judged as it stands now. Returns how many were cleared.
     *
     * @param  list<int>  $movieIds
     * @param  Closure(Builder<Movie>): Builder<Movie>  $condition
     */
    private function clearEach(array $movieIds, Closure $condition): int
    {
        $context = new CatalogContext(config('catalog.language'));
        $cleared = 0;

        foreach ($movieIds as $movieId) {
            DB::transaction(function () use ($movieId, $condition, $context, &$cleared): void {
                $movie = $condition(Movie::query()->whereKey($movieId))->lockForUpdate()->first();

                if ($movie !== null) {
                    $this->clearSourceData($movie, $context);
                    $cleared++;
                }
            });
        }

        return $cleared;
    }

    /**
     * Deletes up to `$limit` Movies nobody holds and nobody has viewed for `purge_unviewed.after_days`, with their
     * genre links, Availability (both cascade), detail cache and TMDB rating, and returns how many it considered. The
     * candidates are locked, then checked again for facts, because saving a Movie needs a lock that conflicts with
     * ours: a fact saved before the lock is seen by the check, and none can be saved after it until the delete. Two
     * races inside the delete's transaction are accepted: a save that loaded the row just before fails once, and
     * trying again re-creates the row from its catalog id; a page view that loaded it renders, or is a 404 if it
     * needed a refresh, and its URL stays a 404 like any purged Movie's.
     */
    private function purgeUnviewed(int $limit): int
    {
        if (! config('catalog.purge_unviewed.enabled')) {
            return 0;
        }

        $candidateIds = $this->movieIds($this->unviewedAndUnheld(Movie::query())->orderBy('id')->limit($limit));

        if ($candidateIds === []) {
            return 0;
        }

        $purged = DB::transaction(function () use ($candidateIds): array {
            Movie::query()->whereKey($candidateIds)->orderBy('id')->lockForUpdate()->get(['id']);
            $movies = $this->unviewedAndUnheld(Movie::query()->whereKey($candidateIds))->get(['id', 'tmdb_id']);
            Movie::query()->whereKey($movies->modelKeys())->delete();
            $tmdbIds = array_values($movies->map(fn (Movie $movie): int => $movie->tmdb_id)->all());
            $this->ratings->forgetMany(RatingSourceName::Tmdb, array_map(fn (int $tmdbId): string => TmdbRatingId::for('movie', $tmdbId), $tmdbIds));

            return $tmdbIds;
        });

        $context = new CatalogContext(config('catalog.language'));

        foreach ($purged as $tmdbId) {
            Cache::forget($this->detailsKey($tmdbId, $context));
            $this->forgetImdbId($tmdbId, $context);
        }

        return count($candidateIds);
    }

    /**
     * Clears everything taken from the Catalog source and marks the Movie removed, so it is never fetched again. Runs
     * inside the refresh's transaction, under the Movie's row lock.
     */
    private function markRemoved(Movie $movie, CatalogContext $context): void
    {
        $this->clearSourceData($movie, $context, ['removed_at' => now()]);
    }

    /**
     * @param  Builder<Movie>  $query
     * @return Builder<Movie>
     */
    private function unviewedAndUnheld(Builder $query): Builder
    {
        return $query->where('last_viewed_at', '<', now()->subDays(config('catalog.purge_unviewed.after_days')))->whereDoesntHave('userMovies');
    }

    /**
     * Keeps the row, so users' facts about the Movie survive, and clears everything taken from the Catalog source:
     * the TMDB-derived columns, genre links, Availability, the detail cache and the TMDB rating. Runs under the
     * Movie's row lock.
     *
     * @param  array<string, mixed>  $extraAttributes  other columns to set with the clearing
     */
    private function clearSourceData(Movie $movie, CatalogContext $context, array $extraAttributes = []): void
    {
        $movie->update([...$this->clearedSourceAttributes($movie), ...$extraAttributes]);
        $movie->genres()->detach();
        $movie->availabilities()->delete();
        $this->ratings->forget(RatingSourceName::Tmdb, TmdbRatingId::for('movie', $movie->tmdb_id));
        Cache::forget($this->detailsKey($movie->tmdb_id, $context));
        $this->forgetImdbId($movie->tmdb_id, $context);
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

    /**
     * What is remembered of the catalog ids' IMDb ids: a string, null for a Movie the source has none for, and no
     * entry for one not remembered.
     *
     * @param  list<int>  $tmdbIds
     * @return array<int, string|null>
     */
    private function rememberedImdbIds(array $tmdbIds): array
    {
        $context = new CatalogContext(config('catalog.language'));
        $generation = $this->purgeGeneration($context);
        $entries = Cache::many(array_map(fn (int $tmdbId): string => $this->imdbIdKey($tmdbId, $context, $generation), $tmdbIds));
        $remembered = [];

        foreach ($tmdbIds as $tmdbId) {
            $entry = $entries[$this->imdbIdKey($tmdbId, $context, $generation)] ?? null;

            if (is_array($entry) && array_key_exists('imdbId', $entry)) {
                $remembered[$tmdbId] = is_string($entry['imdbId']) ? $entry['imdbId'] : null;
            }
        }

        return $remembered;
    }

    private function rememberImdbId(int $tmdbId, ?string $imdbId, CatalogContext $context, ?int $generation = null): void
    {
        Cache::put(
            $this->imdbIdKey($tmdbId, $context, $generation ?? $this->purgeGeneration($context)),
            ['imdbId' => $imdbId],
            $imdbId === null ? now()->addSeconds(config('catalog.imdb_lookup.missing_lifetime_seconds')) : now()->addDays(config('catalog.imdb_lookup.lifetime_days')),
        );
    }

    private function forgetImdbId(int $tmdbId, CatalogContext $context): void
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

    private function imdbIdKey(int $tmdbId, CatalogContext $context, int $generation): string
    {
        return $this->keys->build('imdb-id', ['tmdb_id' => $tmdbId, 'generation' => $generation], $context);
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
