<?php

namespace App\Actions;

use App\Models\Movie;
use App\Models\User;
use App\Models\UserMovie;

class RemoveFromWatchlist
{
    public function __construct(private RecordEvent $recordEvent) {}

    /**
     * Clears only the Watchlist fact. A Movie that isn't on the Watchlist is left alone and records no event.
     */
    public function handle(User $user, Movie $movie, string $source): void
    {
        $facts = UserMovie::change($user, $movie, function (UserMovie $row): void {
            $row->watchlisted_at = null;
        });

        if ($facts['before'] !== $facts['after']) {
            $this->recordEvent->handle($user, 'movie_unwatchlisted', ['movie_id' => $movie->id, 'source' => $source]);
        }
    }
}
