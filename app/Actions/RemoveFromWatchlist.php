<?php

namespace App\Actions;

use App\Enums\AnalyticsEvent;
use App\Enums\Surface;
use App\Models\Movie;
use App\Models\User;
use App\Models\UserMovie;

class RemoveFromWatchlist
{
    public function __construct(private RecordEvent $recordEvent) {}

    /**
     * Clears only the Watchlist fact. A Movie that isn't on the Watchlist is left alone and records no event.
     * Returns the facts before and after.
     *
     * @return array{before: array{watchlisted_at: ?string, watched_at: ?string}, after: array{watchlisted_at: ?string, watched_at: ?string}}
     */
    public function handle(User $user, Movie $movie, Surface $source): array
    {
        $facts = UserMovie::change($user, $movie, function (UserMovie $row): void {
            $row->watchlisted_at = null;
        });

        if ($facts['before'] !== $facts['after']) {
            $this->recordEvent->handle($user, AnalyticsEvent::MovieUnwatchlisted, ['movie_id' => $movie->id, 'source' => $source->value]);
        }

        return $facts;
    }
}
