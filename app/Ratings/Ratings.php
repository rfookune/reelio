<?php

namespace App\Ratings;

use App\Models\Movie;
use Illuminate\Support\Collection;

/**
 * Collects the Rating sources and returns each Movie's External ratings in the fixed display order:
 * IMDb, then TMDB. Dropping a source from the list removes its display without touching any page.
 */
class Ratings
{
    /** @param iterable<RatingSource> $sources */
    public function __construct(private iterable $sources) {}

    /**
     * @param  Collection<int, Movie>  $movies
     * @return array<int, list<ExternalRating>> keyed by Movie id; every given Movie has an entry
     */
    public function ratingsFor(Collection $movies): array
    {
        $result = array_fill_keys($movies->pluck('id')->all(), []);

        foreach ($this->sources as $source) {
            foreach ($source->ratingsFor($movies) as $movieId => $rating) {
                $result[$movieId][] = $rating;
            }
        }

        return array_map(function (array $ratings): array {
            usort($ratings, fn (ExternalRating $a, ExternalRating $b): int => $a->source->displayOrder() <=> $b->source->displayOrder());

            return $ratings;
        }, $result);
    }
}
