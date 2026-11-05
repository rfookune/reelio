<?php

namespace App\Catalog;

use App\Models\Genre;
use App\Models\Movie;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class Catalog
{
    public function __construct(private CatalogSource $source, private CacheKey $keys) {}

    public function findOrCreate(int $tmdbId, string $actor): ?Movie
    {
        $existing = Movie::query()->where('tmdb_id', $tmdbId)->first();

        if ($existing !== null) {
            return $existing;
        }

        $context = new CatalogContext(config('catalog.language'));
        $this->limitCreation($actor, $context);
        $details = $this->source->movie($tmdbId, $context);

        if ($details === null) {
            return null;
        }

        $movie = DB::transaction(function () use ($details): Movie {
            $movie = Movie::query()->createOrFirst(['tmdb_id' => $details->tmdbId], [
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
                'vote_average' => $details->voteAverage,
                'vote_count' => $details->voteCount,
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

        Cache::put($this->keys->build('movie', ['tmdb_id' => $tmdbId], $context), $details, now()->addDays(30));

        return $movie;
    }

    public function find(int $id): Movie
    {
        return Movie::query()->findOrFail($id);
    }

    public function recordView(Movie $movie): void
    {
        $movie->update(['last_viewed_at' => now()]);
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
