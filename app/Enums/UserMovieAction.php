<?php

namespace App\Enums;

/**
 * The Watchlist and Watched actions a user can take on a Movie, and so undo; the value names the action in the
 * Undo request and its analytics event.
 */
enum UserMovieAction: string
{
    case AddToWatchlist = 'add_to_watchlist';
    case RemoveFromWatchlist = 'remove_from_watchlist';
    case MarkAsWatched = 'mark_as_watched';
    case ClearWatched = 'clear_watched';
}
