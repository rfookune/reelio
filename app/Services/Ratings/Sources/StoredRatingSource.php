<?php

namespace App\Services\Ratings\Sources;

use App\Services\Ratings\ExternalRating;

use App\Services\Ratings\Enums\RatingSourceName;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * One Rating source's scores from the `ratings` table, joined through the identifier that source uses for a
 * Movie. A Movie without that identifier, or without a row, has no rating; there is never a title search.
 * PHP turns numeric array keys (TMDB ids) back into ints, so the lookup binds them as strings to match the
 * `source_id` column.
 */
class StoredRatingSource implements RatingSource
{
    public function __construct(private RatingSourceName $source) {}

    public function ratingsFor(Collection $movies): array
    {
        if (! $this->source->isEnabled()) {
            return [];
        }

        $movieIdsBySourceId = [];

        foreach ($movies as $movie) {
            $sourceId = $this->source->sourceIdFor($movie);

            if ($sourceId !== null) {
                $movieIdsBySourceId[$sourceId][] = $movie->id;
            }
        }

        if ($movieIdsBySourceId === []) {
            return [];
        }

        $ratings = [];
        $rows = DB::table('ratings')
            ->where('source', $this->source->value)
            ->whereIn('source_id', array_map('strval', array_keys($movieIdsBySourceId)))
            ->get();

        foreach ($rows as $row) {
            $rating = new ExternalRating($this->source, (float) $row->value, (int) $row->vote_count, CarbonImmutable::parse($row->as_of));

            foreach ($movieIdsBySourceId[$row->source_id] as $movieId) {
                $ratings[$movieId] = $rating;
            }
        }

        return $ratings;
    }
}
