<?php

namespace App\Services\Catalog\Titles;

use App\Models\Genre;
use App\Models\Movie;
use App\Services\Catalog\CacheKey;
use App\Services\Catalog\CatalogContext;
use App\Services\Catalog\CatalogResult;
use App\Services\Catalog\MisconfigurationReporter;
use App\Services\Catalog\Search\ImdbIdLookup;
use App\Services\Catalog\Sources\CatalogSource;
use App\Services\Catalog\Sources\Details\MovieDetails;
use App\Services\Catalog\Sources\SourceUnavailable;
use App\Services\Ratings\Enums\RatingSourceName;
use App\Services\Ratings\RatingStore;
use App\Services\Ratings\TmdbRatingId;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MovieCatalog
{
    public function __construct(private CatalogSource $source, private CacheKey $keys, private RatingStore $ratings, private AvailabilityWriter $availability, private MisconfigurationReporter $misconfiguration, private CreationLimiter $creation, private ImdbIdLookup $imdb) {}

    /** @return CatalogResult<Movie> */
    public function findOrCreate(int $tmdbId, string $actor): CatalogResult
    {
        $existing = $this->findByCatalogId($tmdbId);

        if ($existing !== null) {
            return CatalogResult::fresh($existing);
        }

        $context = new CatalogContext(config('catalog.language'));
        $this->creation->limitCreation($actor, $context);

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
        $this->imdb->rememberImdbId($details->tmdbId, $details->imdbId, $context);
        $this->ratings->record(RatingSourceName::Tmdb, TmdbRatingId::for('movie', $details->tmdbId), $details->voteAverage, $details->voteCount);

        return CatalogResult::fresh($movie);
    }

    /** The stored Movie for a catalog id, without asking the source. */
    public function findByCatalogId(int $tmdbId): ?Movie
    {
        return Movie::query()->where('tmdb_id', $tmdbId)->first();
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
            $this->imdb->rememberImdbId($movie->tmdb_id, $details->imdbId, $context);

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

    public function recordView(Movie $movie): void
    {
        $movie->update(['last_viewed_at' => now()]);
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
     * Clears each of the Movies that still matches `$condition` once its row is locked, so a Movie a refresh has just
     * changed is judged as it stands now. Returns how many were cleared.
     *
     * @param  list<int>  $movieIds
     * @param  Closure(Builder<Movie>): Builder<Movie>  $condition
     */
    public function clearEach(array $movieIds, Closure $condition): int
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
     * Clears everything taken from the Catalog source and marks the Movie removed, so it is never fetched again. Runs
     * inside the refresh's transaction, under the Movie's row lock.
     */
    private function markRemoved(Movie $movie, CatalogContext $context): void
    {
        $this->clearSourceData($movie, $context, ['removed_at' => now()]);
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
        $this->imdb->forgetImdbId($movie->tmdb_id, $context);
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

    public function forgetCachedData(int $tmdbId, CatalogContext $context): void
    {
        Cache::forget($this->detailsKey($tmdbId, $context));
        $this->imdb->forgetImdbId($tmdbId, $context);
    }
}
