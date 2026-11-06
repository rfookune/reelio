<?php

namespace App\Actions;

use App\Models\Movie;
use App\Models\User;
use App\Models\UserMovie;

class ClearWatched
{
    public function __construct(private RecordEvent $recordEvent) {}

    /**
     * Clears only the Watched fact; the Watchlist is untouched. A Movie that isn't Watched is left alone and
     * records no event.
     * Returns the facts before and after.
     *
     * @return array{before: array{watchlisted_at: ?string, watched_at: ?string}, after: array{watchlisted_at: ?string, watched_at: ?string}}
     */
    public function handle(User $user, Movie $movie, string $source): array
    {
        $facts = UserMovie::change($user, $movie, function (UserMovie $row): void {
            $row->watched_at = null;
        });

        if ($facts['before'] !== $facts['after']) {
            $this->recordEvent->handle($user, 'movie_unmarked_watched', [
                'movie_id' => $movie->id,
                'source' => $source,
                'was_on_watchlist' => $facts['after']['watchlisted_at'] !== null,
            ]);
        }

        return $facts;
    }
}
