<?php

namespace App\Http\Support;

/**
 * What narrows a Movie picker pick: any-match genres (none means any Movie) and the previous pick, left out when
 * another Movie qualifies.
 */
readonly class PickerCriteria
{
    /**
     * @param  list<int>  $genreIds  TMDB genre ids.
     * @param  int|null  $previousTmdbId  The catalog id of the Movie picked last.
     */
    public function __construct(public array $genreIds = [], public ?int $previousTmdbId = null) {}
}
