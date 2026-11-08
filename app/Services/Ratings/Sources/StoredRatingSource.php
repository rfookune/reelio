<?php

namespace App\Services\Ratings\Sources;

use App\Services\Ratings\Enums\RatingSourceName;
use App\Services\Ratings\ExternalRating;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * One Rating source's scores from the `ratings` table, joined through the identifier that source uses for a
 * Title. A Title without that identifier, or without a row, has no rating; there is never a title search.
 */
class StoredRatingSource implements RatingSource
{
    public function __construct(private RatingSourceName $source) {}

    public function ratingsFor(Collection $titles): array
    {
        if (! $this->source->isEnabled()) {
            return [];
        }

        $titleIdsBySourceId = [];

        foreach ($titles as $title) {
            $sourceId = $this->source->sourceIdFor($title);

            if ($sourceId !== null) {
                $titleIdsBySourceId[$sourceId][] = $title->id;
            }
        }

        if ($titleIdsBySourceId === []) {
            return [];
        }

        $ratings = [];
        $rows = DB::table('ratings')
            ->where('source', $this->source->value)
            ->whereIn('source_id', array_map('strval', array_keys($titleIdsBySourceId)))
            ->get();

        foreach ($rows as $row) {
            $rating = new ExternalRating($this->source, (float) $row->value, (int) $row->vote_count, CarbonImmutable::parse($row->as_of));

            foreach ($titleIdsBySourceId[$row->source_id] as $titleId) {
                $ratings[$titleId] = $rating;
            }
        }

        return $ratings;
    }
}
