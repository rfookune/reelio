<?php

namespace App\Ratings\Sync\Datasets;

use App\Ratings\Enums\RatingSourceName;

/**
 * The registered full-dataset Rating sources.
 */
class RatingDatasets
{
    /** @param iterable<RatingDataset> $datasets */
    public function __construct(private iterable $datasets) {}

    /** @return list<RatingDataset> the datasets whose source is switched on */
    public function enabled(): array
    {
        $enabled = [];

        foreach ($this->datasets as $dataset) {
            if ($dataset->source()->isEnabled()) {
                $enabled[] = $dataset;
            }
        }

        return $enabled;
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
