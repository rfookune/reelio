<?php

namespace App\Services\Catalog\Maintenance;

use App\Models\Genre;
use App\Models\Movie;
use App\Services\Catalog\CacheKey;
use App\Services\Catalog\CatalogContext;
use App\Services\Catalog\Discover\DiscoverBuilder;
use App\Services\Catalog\Titles\AvailabilityWriter;
use App\Services\Catalog\Titles\MovieCatalog;
use App\Services\Ratings\Enums\RatingSourceName;
use App\Services\Ratings\RatingStore;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

class CatalogPurge
{
    public function __construct(private RatingStore $ratings, private AvailabilityWriter $availability, private MovieCatalog $movies, private DiscoverBuilder $discover, private CacheKey $keys) {}

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
            $cleared += $this->movies->clearEach($movieIds, $withDetails);
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

    /**
     * @param  Builder<Movie>  $query
     * @return list<int>
     */
    private function movieIds(Builder $query): array
    {
        return array_values($query->get(['id'])->map(fn (Movie $movie): int => $movie->id)->all());
    }
}
