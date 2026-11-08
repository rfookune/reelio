<?php

namespace App\Services\Catalog\Sources\Details;

readonly class SeasonDetails
{
    /** @param list<EpisodeDetails> $episodes */
    public function __construct(
        public int $tmdbId,
        public int $number,
        public ?string $name = null,
        public ?string $overview = null,
        public ?string $posterPath = null,
        public ?string $airDate = null,
        public ?int $episodeCount = null,
        public array $episodes = [],
    ) {}
}
