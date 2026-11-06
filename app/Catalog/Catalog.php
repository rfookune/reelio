<?php

namespace App\Catalog;

use App\Models\Genre;
use App\Models\Movie;
use App\Ratings\RatingSourceName;
use App\Ratings\RatingStore;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class Catalog
{
    private const DETAILS_FRESH_SECONDS = 86400;

    private const DETAILS_HARD_LIFETIME_DAYS = 30;

    public function __construct(private CatalogSource $source, private CacheKey $keys, private RatingStore $ratings) {}

    /** @return CatalogResult<Movie> */
    public function findOrCreate(int $tmdbId, string $actor): CatalogResult
    {
        $existing = Movie::query()->where('tmdb_id', $tmdbId)->first();

        if ($existing !== null) {
            return CatalogResult::fresh($existing);
        }

        $context = new CatalogContext(config('catalog.language'));
        $this->limitCreation($actor, $context);

        try {
            $details = $this->source->movie($tmdbId, $context);
        } catch (SourceUnavailable $exception) {
            $this->reportIfMisconfigured($exception, $context);

            return CatalogResult::unavailable();
        }

        if ($details === null) {
            return CatalogResult::fresh(null);
        }

        $movie = DB::transaction(function () use ($details): Movie {
            $movie = Movie::query()->firstOrCreate(['tmdb_id' => $details->tmdbId], [
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
                'last_viewed_at' => now(),
            ]);

            if ($movie->wasRecentlyCreated) {
                foreach ($details->genres as $genre) {
                    Genre::query()->updateOrCreate(['id' => $genre['id']], ['name' => $genre['name']]);
                }
                $movie->genres()->sync(array_column($details->genres, 'id'));
            }

            return $movie;
        });

        $this->storeDetails($this->detailsKey($tmdbId, $context), $details);
        $this->ratings->record(RatingSourceName::Tmdb, (string) $details->tmdbId, $details->voteAverage, $details->voteCount);

        return CatalogResult::fresh($movie);
    }

    public function find(int $id): Movie
    {
        return Movie::query()->findOrFail($id);
    }

    /**
     * The Movie's Catalog source details. A cached entry younger than a day is fresh; an older one is refreshed
     * in the request, and served as stale if the source fails. With no entry and no source, the result is unavailable.
     *
     * @return CatalogResult<MovieDetails>
     */
    public function details(Movie $movie): CatalogResult
    {
        $context = new CatalogContext(config('catalog.language'));
        $key = $this->detailsKey($movie->tmdb_id, $context);
        $entry = $this->cachedDetails($key);

        if ($entry !== null && $entry['storedAt'] > now()->subSeconds(self::DETAILS_FRESH_SECONDS)->getTimestamp()) {
            return CatalogResult::fresh($entry['details']);
        }

        try {
            $details = $this->source->movie($movie->tmdb_id, $context);
        } catch (SourceUnavailable $exception) {
            $this->reportIfMisconfigured($exception, $context);

            return $entry !== null ? CatalogResult::stale($entry['details']) : CatalogResult::unavailable();
        }

        if ($details !== null) {
            $this->storeDetails($key, $details);
            $this->ratings->record(RatingSourceName::Tmdb, (string) $details->tmdbId, $details->voteAverage, $details->voteCount);
        }

        return CatalogResult::fresh($details);
    }

    public function recordView(Movie $movie): void
    {
        $movie->update(['last_viewed_at' => now()]);
    }

    private function detailsKey(int $tmdbId, CatalogContext $context): string
    {
        return $this->keys->build('movie', ['tmdb_id' => $tmdbId], $context);
    }

    private function storeDetails(string $key, MovieDetails $details): void
    {
        Cache::put($key, ['storedAt' => now()->getTimestamp(), 'details' => $details->toArray()], now()->addDays(self::DETAILS_HARD_LIFETIME_DAYS));
    }

    /** @return array{storedAt: int, details: MovieDetails}|null */
    private function cachedDetails(string $key): ?array
    {
        $cached = Cache::get($key);

        if (! is_array($cached) || ! is_int($cached['storedAt'] ?? null) || ! is_array($cached['details'] ?? null)) {
            return null;
        }

        $details = MovieDetails::fromArray($cached['details']);

        return $details === null ? null : ['storedAt' => $cached['storedAt'], 'details' => $details];
    }

    /** Reports a rejected token to the owner, at most once every five minutes. */
    private function reportIfMisconfigured(SourceUnavailable $exception, CatalogContext $context): void
    {
        if ($exception instanceof SourceMisconfigured && Cache::add($this->keys->build('misconfigured-reported', [], $context), true, 300)) {
            report($exception);
        }
    }

    private function limitCreation(string $actor, CatalogContext $context): void
    {
        $parameters = ['actor' => $actor];
        $key = $this->keys->build('creation', $parameters, $context);
        $acquired = Cache::lock($this->keys->build('creation-lock', $parameters, $context), 5)->get(function () use ($key): bool {
            if (RateLimiter::tooManyAttempts($key, 20)) {
                throw new CreationLimited(max(1, RateLimiter::availableIn($key)));
            }

            RateLimiter::hit($key, 60);

            return true;
        });

        if (! $acquired) {
            throw new CreationLimited(1);
        }
    }
}
