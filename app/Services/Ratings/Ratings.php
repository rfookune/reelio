<?php

namespace App\Services\Ratings;

use App\Models\Title;
use App\Services\Ratings\Sources\RatingSource;
use Illuminate\Support\Collection;

/**
 * Collects the Rating sources and returns each Title's External ratings in the fixed display order:
 * IMDb, then TMDB. Dropping a source from the list removes its display without touching any page.
 */
class Ratings
{
    /** @param iterable<RatingSource> $sources */
    public function __construct(private iterable $sources) {}

    /**
     * @template TTitle of Title
     *
     * @param  Collection<int, TTitle>  $titles
     * @return array<int, list<ExternalRating>> keyed by Title id; every given Title has an entry
     */
    public function ratingsFor(Collection $titles): array
    {
        $result = array_fill_keys($titles->pluck('id')->all(), []);

        foreach ($this->sources as $source) {
            foreach ($source->ratingsFor($titles) as $titleId => $rating) {
                $result[$titleId][] = $rating;
            }
        }

        return array_map(function (array $ratings): array {
            usort($ratings, fn (ExternalRating $a, ExternalRating $b): int => $a->source->displayOrder() <=> $b->source->displayOrder());

            return $ratings;
        }, $result);
    }
}
