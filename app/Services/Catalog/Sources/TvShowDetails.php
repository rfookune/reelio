<?php

namespace App\Services\Catalog\Sources;

readonly class TvShowDetails
{
    /**
     * @param  list<array{id: int, name: string}>  $genres
     * @param  list<string>  $creators
     * @param  list<string>  $networks
     * @param  list<array{id: int, name: string, character: ?string, profilePath: ?string}>  $cast
     * @param  array{key: string, name: string, type: string}|null  $trailer
     * @param  list<array{serviceId: int, serviceName: string, logoPath: ?string, country: string, offerType: string, displayPriority: int}>  $availability
     * @param  list<SeasonDetails>  $seasons
     */
    public function __construct(
        public int $tmdbId,
        public ?string $title = null,
        public ?string $originalTitle = null,
        public ?string $overview = null,
        public ?string $tagline = null,
        public ?string $firstAirDate = null,
        public ?string $lastAirDate = null,
        public ?string $status = null,
        public ?int $seasonCount = null,
        public ?int $episodeCount = null,
        public ?string $posterPath = null,
        public ?string $backdropPath = null,
        public ?string $originalLanguage = null,
        public ?float $voteAverage = null,
        public ?int $voteCount = null,
        public ?string $imdbId = null,
        public array $genres = [],
        public array $creators = [],
        public array $networks = [],
        public array $cast = [],
        public ?string $certification = null,
        public ?array $trailer = null,
        public array $availability = [],
        public array $seasons = [],
    ) {}

}
