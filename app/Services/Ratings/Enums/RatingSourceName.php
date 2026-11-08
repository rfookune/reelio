<?php

namespace App\Services\Ratings\Enums;

use App\Models\Title;
use App\Services\Ratings\TmdbRatingId;

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

    /** The identifier this source uses for the Title, or null when the Title has none. */
    public function sourceIdFor(Title $title): ?string
    {
        $identifier = match ($this) {
            self::Imdb => $title->imdb_id,
            self::Tmdb => $title->tmdb_id,
        };

        if ($identifier === null || $identifier === '') {
            return null;
        }

        return $this === self::Tmdb ? TmdbRatingId::for($title->kind, $title->tmdb_id) : (string) $identifier;
    }

    /** A switched-off source is hidden everywhere and isn't synced. */
    public function isEnabled(): bool
    {
        return (bool) config("ratings.sources.{$this->value}.enabled", true);
    }
}
