<?php

namespace App\Services\Catalog\Titles;

use App\Models\Genre;
use App\Models\TvShow;
use App\Services\Catalog\Sources\Details\TvShowDetails;
use App\Services\Ratings\Enums\RatingSourceName;
use App\Services\Ratings\RatingStore;
use App\Services\Ratings\TmdbRatingId;
use Illuminate\Support\Str;

/** Writes a complete TV Show under the caller's transaction and Title row lock. */
class TvShowWriter
{
    public function __construct(private AvailabilityWriter $availability, private RatingStore $ratings) {}

    public function store(TvShow $tvShow, TvShowDetails $details): void
    {
        $tvShow->update($this->sourceAttributes($details));
        $tvShow->details()->updateOrCreate([], $this->metadataAttributes($details));
        if ($details->genres !== []) {
            Genre::query()->upsert($details->genres, ['id'], ['name']);
        }
        $tvShow->genres()->sync(array_column($details->genres, 'id'));
        $this->availability->replace($tvShow, $details->availability);
        $this->ratings->record(RatingSourceName::Tmdb, TmdbRatingId::for('tv', $tvShow->tmdb_id), $details->voteAverage, $details->voteCount);

        $seasonIds = [];
        $episodeIds = [];
        foreach ($details->seasons as $detailsSeason) {
            $season = $tvShow->seasons()->updateOrCreate(['tmdb_id' => $detailsSeason->tmdbId], [
                'season_number' => $detailsSeason->number, 'name' => $detailsSeason->name,
                'overview' => $detailsSeason->overview, 'poster_path' => $detailsSeason->posterPath,
                'air_date' => $detailsSeason->airDate, 'episode_count' => $detailsSeason->episodeCount, 'removed_at' => null,
            ]);
            $seasonIds[] = $season->id;
            foreach ($detailsSeason->episodes as $detailsEpisode) {
                $episode = $tvShow->episodes()->updateOrCreate(['tmdb_id' => $detailsEpisode->tmdbId], [
                    'season_id' => $season->id, 'season_number' => $detailsEpisode->seasonNumber,
                    'episode_number' => $detailsEpisode->number, 'name' => $detailsEpisode->name,
                    'overview' => $detailsEpisode->overview, 'air_date' => $detailsEpisode->airDate,
                    'runtime' => $detailsEpisode->runtime, 'episode_type' => $detailsEpisode->episodeType, 'removed_at' => null,
                ]);
                $episodeIds[] = $episode->id;
            }
        }
        $tvShow->seasons()->whereNotIn('id', $seasonIds)->whereNull('removed_at')->update(['removed_at' => now()]);
        $tvShow->episodes()->whereNotIn('id', $episodeIds)->whereNull('removed_at')->update(['removed_at' => now()]);
    }

    public function markRemoved(TvShow $tvShow): void
    {
        $empty = new TvShowDetails($tvShow->tmdb_id);
        $tvShow->update([
            ...array_fill_keys(array_keys($this->sourceAttributes($empty)), null),
            'removed_at' => now(),
        ]);
        $tvShow->details()->update(array_fill_keys(array_keys($this->metadataAttributes($empty)), null));
        $tvShow->genres()->detach();
        $tvShow->availabilities()->delete();
        $this->ratings->forget(RatingSourceName::Tmdb, TmdbRatingId::for('tv', $tvShow->tmdb_id));
        $tvShow->seasons()->update([
            'season_number' => null, 'name' => null, 'overview' => null, 'poster_path' => null,
            'air_date' => null, 'episode_count' => null, 'removed_at' => now(),
        ]);
        $tvShow->episodes()->update([
            'season_number' => null, 'episode_number' => null, 'name' => null, 'overview' => null,
            'air_date' => null, 'runtime' => null, 'episode_type' => null, 'removed_at' => now(),
        ]);
    }

    /** @return array<string, mixed> */
    private function sourceAttributes(TvShowDetails $details): array
    {
        return [
            'slug' => Str::slug($details->title ?? '') ?: 'tv-show',
            'title' => $details->title, 'original_title' => $details->originalTitle,
            'overview' => $details->overview, 'tagline' => $details->tagline,
            'poster_path' => $details->posterPath, 'backdrop_path' => $details->backdropPath,
            'original_language' => $details->originalLanguage, 'imdb_id' => $details->imdbId, 'synced_at' => now(),
        ];
    }

    /** @return array<string, mixed> */
    private function metadataAttributes(TvShowDetails $details): array
    {
        return [
            'first_air_date' => $details->firstAirDate, 'last_air_date' => $details->lastAirDate,
            'status' => $details->status, 'season_count' => $details->seasonCount, 'episode_count' => $details->episodeCount,
        ];
    }
}
