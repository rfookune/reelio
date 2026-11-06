<?php

namespace App\Actions;

use App\Models\Movie;
use App\Models\User;
use App\Models\UserMovie;
use Illuminate\Support\Facades\DB;

class RemoveFromWatchlist
{
    public function __construct(private RecordEvent $recordEvent) {}

    /**
     * Clears only the Watchlist fact, deleting the row when it held nothing else. A Movie that isn't on the
     * Watchlist is left alone and records no event. The row is locked while deciding, so a concurrent action on
     * the same Movie waits and then sees what this one left.
     */
    public function handle(User $user, Movie $movie, string $source): void
    {
        $removed = DB::transaction(function () use ($user, $movie): bool {
            $facts = UserMovie::query()->whereBelongsTo($user)->whereBelongsTo($movie)->lockForUpdate()->first();

            if ($facts?->watchlisted_at === null) {
                return false;
            }

            $facts->watched_at === null ? $facts->delete() : $facts->update(['watchlisted_at' => null]);

            return true;
        });

        if ($removed) {
            $this->recordEvent->handle($user, 'movie_unwatchlisted', ['movie_id' => $movie->id, 'source' => $source]);
        }
    }
}
