<?php

namespace App\Actions;

use App\Models\Movie;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AddToWatchlist
{
    public function __construct(private RecordEvent $recordEvent) {}

    /**
     * Sets the Date added to now. A Movie already on the Watchlist keeps its original Date added and records no
     * event. One upsert does it: it inserts the row, or sets the Date added only where there was none, and returns
     * the row only when it changed, so concurrent actions on the same Movie can't lose this one.
     */
    public function handle(User $user, Movie $movie, string $source): void
    {
        $added = DB::select(
            'insert into user_movies (user_id, movie_id, watchlisted_at) values (?, ?, ?)
            on conflict (user_id, movie_id) do update set watchlisted_at = excluded.watchlisted_at
            where user_movies.watchlisted_at is null
            returning id',
            [$user->id, $movie->id, now()],
            useReadPdo: false,
        ) !== [];

        if ($added) {
            $this->recordEvent->handle($user, 'movie_watchlisted', ['movie_id' => $movie->id, 'source' => $source]);
        }
    }
}
