<?php

namespace App\Services\Catalog;

use App\Services\Catalog\Sources\MovieDetails;
use App\Services\Catalog\Sources\MovieListing;
use Error;

/**
 * One Movie in the Discover payload: its listing, completed with its details when they could be fetched.
 */
readonly class DiscoverMovie
{
    /**
     * @param  list<string>  $genres
     * @param  array{key: string, name: string, type: string}|null  $trailer
     */
    public function __construct(
        public int $tmdbId,
        public ?string $title = null,
        public ?string $overview = null,
        public ?string $releaseDate = null,
        public ?int $runtime = null,
        public ?string $posterPath = null,
        public ?string $backdropPath = null,
        public ?string $imdbId = null,
        public array $genres = [],
        public ?array $trailer = null,
    ) {}

    public static function fromListing(MovieListing $listing, ?MovieDetails $details = null): self
    {
        return new self(
            tmdbId: $listing->tmdbId,
            title: $listing->title,
            overview: $listing->overview,
            releaseDate: $listing->releaseDate,
            runtime: $details?->runtime,
            posterPath: $listing->posterPath,
            backdropPath: $listing->backdropPath,
            imdbId: $details?->imdbId,
            genres: array_column($details->genres ?? [], 'name'),
            trailer: $details?->trailer,
        );
    }

    /** This Movie's listing with another version's details, such as the previous build's. */
    public function withDetailsOf(self $other): self
    {
        return new self(
            tmdbId: $this->tmdbId,
            title: $this->title,
            overview: $this->overview,
            releaseDate: $this->releaseDate,
            runtime: $other->runtime,
            posterPath: $this->posterPath,
            backdropPath: $this->backdropPath,
            imdbId: $other->imdbId,
            genres: $other->genres,
            trailer: $other->trailer,
        );
    }

    /**
     * Cache-safe form: the cache store does not unserialize objects.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }

    /** The Movie from its cached form, or null when that form no longer fits. */
    public static function fromArray(mixed $data): ?self
    {
        try {
            return is_array($data) ? new self(...$data) : null;
        } catch (Error) {
            return null;
        }
    }
}
