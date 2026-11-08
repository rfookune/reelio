<?php

namespace App\Enums;

/**
 * Whether a Title is a Movie or a TV Show; the value is what the `titles.kind` column stores.
 */
enum TitleKind: string
{
    case Movie = 'movie';
    case TvShow = 'tv';
}
