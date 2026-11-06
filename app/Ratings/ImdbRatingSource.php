<?php

namespace App\Ratings;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * IMDb ratings from the imported non-commercial dataset, joined through `imdb_id`. A Movie without an
 * `imdb_id` or without an entry has no IMDb rating; there is never a title-search fallback.
 */
class ImdbRatingSource implements RatingSource
{
    public function ratingsFor(Collection $movies): array
    {
        if (! config('ratings.imdb.enabled')) {
            return [];
        }

        $movieIdsByImdbId = [];

        foreach ($movies as $movie) {
            if (is_string($movie->imdb_id) && $movie->imdb_id !== '') {
                $movieIdsByImdbId[$movie->imdb_id][] = $movie->id;
            }
        }

        if ($movieIdsByImdbId === []) {
            return [];
        }

        $ratings = [];
        $rows = DB::table('imdb_ratings')->whereIn('imdb_id', array_keys($movieIdsByImdbId))->get();

        foreach ($rows as $row) {
            $rating = new ExternalRating(RatingSourceName::Imdb, (float) $row->rating, (int) $row->vote_count, CarbonImmutable::parse($row->imported_at));

            foreach ($movieIdsByImdbId[$row->imdb_id] as $movieId) {
                $ratings[$movieId] = $rating;
            }
        }

        return $ratings;
    }
}
