<?php

namespace App\Actions;

use App\Models\Movie;
use App\Models\User;
use App\Models\UserMovie;

class MarkAsWatched
{
    public function __construct(private RecordEvent $recordEvent) {}

    /**
     * Sets the Watched date to now and takes the Movie off the Watchlist. On a Watched Movie that's on the
     * Watchlist, a rewatch, the date moves. A Watched Movie that isn't on the Watchlist is left alone and records
     * no event. Taking it off the Watchlist records no Watchlist removal.
     */
    public function handle(User $user, Movie $movie, string $source): void
    {
        $facts = UserMovie::change($user, $movie, function (UserMovie $row): void {
            if ($row->watched_at === null || $row->watchlisted_at !== null) {
                $row->watched_at = now();
                $row->watchlisted_at = null;
            }
        });

        if ($facts['before'] !== $facts['after']) {
            $this->recordEvent->handle($user, 'movie_marked_watched', [
                'movie_id' => $movie->id,
                'source' => $source,
                'was_on_watchlist' => $facts['before']['watchlisted_at'] !== null,
            ]);
        }
    }
}
