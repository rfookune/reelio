<?php

namespace App\Enums;

/**
 * Whether a Title is a Movie or a TV Show; the value is what the `titles.kind` column stores.
 */
enum TitleKind: string
{
    case Movie = 'movie';
    case TvShow = 'tv';

    /** The slug a route uses for a Title of this kind that has none yet. */
    public function defaultSlug(): string
    {
        return match ($this) {
            self::Movie => 'movie',
            self::TvShow => 'tv-show',
        };
    }
}
