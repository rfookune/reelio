<?php

namespace App\Services\Catalog\Sources\Details;

readonly class EpisodeDetails
{
    public function __construct(
        public int $tmdbId,
        public int $seasonNumber,
        public int $number,
        public ?string $name = null,
        public ?string $overview = null,
        public ?string $airDate = null,
        public ?int $runtime = null,
        public ?string $episodeType = null,
    ) {}
}
