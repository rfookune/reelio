<?php

namespace App\Services\Catalog\Sources;

readonly class MovieDetails
{
    /**
     * @param  list<array{id: int, name: string}>  $genres
     * @param  list<string>  $directors
     * @param  list<string>  $writers
     * @param  list<array{id: int, name: string, character: ?string, profilePath: ?string}>  $cast
     * @param  array{key: string, name: string, type: string}|null  $trailer
     * @param  list<array{serviceId: int, serviceName: string, logoPath: ?string, country: string, offerType: string, displayPriority: int}>  $availability
     */
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
        public array $directors = [],
        public array $writers = [],
        public array $cast = [],
        public ?string $certification = null,
        public ?string $usReleaseDate = null,
        public ?string $usDigitalReleaseDate = null,
        public ?array $trailer = null,
        public array $availability = [],
    ) {}

    /**
     * Cache-safe form: the cache store does not unserialize objects.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): ?self
    {
        try {
            return new self(...$data);
        } catch (\Error) {
            return null;
        }
    }
}
