<?php

namespace App\Services\Ratings\Sync\Datasets;

use App\Services\Ratings\Enums\RatingSourceName;

/**
 * The registered full-dataset Rating sources.
 */
class RatingDatasets
{
    /** @param iterable<RatingDataset> $datasets */
    public function __construct(private iterable $datasets) {}

    /** @return list<RatingDataset> every registered dataset, its source switched on or off */
    public function all(): array
    {
        $all = [];

        foreach ($this->datasets as $dataset) {
            $all[] = $dataset;
        }

        return $all;
    }

    /** @return list<RatingDataset> the datasets whose source is switched on */
    public function enabled(): array
    {
        return array_values(array_filter($this->all(), fn (RatingDataset $dataset): bool => $dataset->source()->isEnabled()));
    }

    public function for(RatingSourceName $source): ?RatingDataset
    {
        foreach ($this->enabled() as $dataset) {
            if ($dataset->source() === $source) {
                return $dataset;
            }
        }

        return null;
    }
}
