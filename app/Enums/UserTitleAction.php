<?php

namespace App\Enums;

/**
 * The Watchlist and Watched actions a user can take on a Movie, TV Show, Season or Episode.
 * The value names the action in Undo requests, session snapshots and analytics properties.
 */
enum UserTitleAction: string
{
    case AddToWatchlist = 'add_to_watchlist';
    case RemoveFromWatchlist = 'remove_from_watchlist';
    case MarkAsWatched = 'mark_as_watched';
    case ClearWatched = 'clear_watched';
}
