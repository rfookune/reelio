<?php

namespace App\Catalog;

readonly class MovieDetails
{
    /** @param list<array{id: int, name: string}> $genres */
    public function __construct(
        public int $tmdbId,
        public ?string $title = null,
        public ?string $originalTitle = null,
        public ?string $overview = null,
        public ?string $tagline = null,
        public ?string $releaseDate = null,
        public ?int $runtime = null,
        public ?string $posterPath = null,
        public ?string $backdropPath = null,
        public ?string $originalLanguage = null,
        public ?float $voteAverage = null,
        public ?int $voteCount = null,
        public ?string $imdbId = null,
        public array $genres = [],
    ) {}
}
