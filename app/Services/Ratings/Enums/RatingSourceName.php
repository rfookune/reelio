<?php

namespace App\Services\Ratings\Enums;

use App\Models\Movie;

/**
 * The organizations whose scores Reelio displays. Case order is display order.
 */
enum RatingSourceName: string
{
    case Imdb = 'imdb';
    case Tmdb = 'tmdb';

    public function label(): string
    {
        return match ($this) {
            self::Imdb => 'IMDb',
            self::Tmdb => 'TMDB',
        };
    }

    public function displayOrder(): int
    {
        return (int) array_search($this, self::cases(), true);
    }

    /** The identifier this source uses for the Movie's title, or null when the Movie has none. */
    public function sourceIdFor(Movie $movie): ?string
    {
        $identifier = match ($this) {
            self::Imdb => $movie->imdb_id,
            self::Tmdb => $movie->tmdb_id,
        };

        return $identifier === null || $identifier === '' ? null : (string) $identifier;
    }

    /** A switched-off source is hidden everywhere and isn't synced. */
    public function isEnabled(): bool
    {
        return (bool) config("ratings.sources.{$this->value}.enabled", true);
    }
}
