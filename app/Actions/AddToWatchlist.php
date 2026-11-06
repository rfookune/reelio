<?php

namespace App\Actions;

use App\Models\Movie;
use App\Models\User;
use App\Models\UserMovie;

class AddToWatchlist
{
    public function __construct(private RecordEvent $recordEvent) {}

    /**
     * Sets the Date added to now. A Movie already on the Watchlist keeps its original Date added and records no
     * event.
     */
    public function handle(User $user, Movie $movie, string $source): void
    {
        $facts = UserMovie::change($user, $movie, function (UserMovie $row): void {
            $row->watchlisted_at ??= now();
        });

        if ($facts['before'] !== $facts['after']) {
            $this->recordEvent->handle($user, 'movie_watchlisted', ['movie_id' => $movie->id, 'source' => $source]);
        }
    }
}
