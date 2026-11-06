<?php

namespace App\Ratings;

/**
 * The organizations whose scores Reelio displays, in display order.
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
        return match ($this) {
            self::Imdb => 0,
            self::Tmdb => 1,
        };
    }
}
