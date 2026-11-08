<?php

namespace App\Actions;

use App\Enums\AnalyticsEvent;
use App\Enums\UserTitleAction;
use App\Models\Movie;
use App\Models\User;
use App\Models\UserMovie;

class UndoUserMovieAction
{
    public function __construct(private RecordEvent $recordEvent) {}

    /**
     * Restores a user's facts about a Movie to what they were before an action, including the earlier dates or no
     * row at all, but only if they still stand where the action left them. With no Movie row, which happens only
     * when the action left no facts and there are none to restore, the facts stand at none. Returns whether they
     * stood there. An undo that changes something records `user_movie_action_undone`; the action's own event
     * stays.
     *
     * @param  array{before: array{watchlisted_at: ?string, watched_at: ?string}, after: array{watchlisted_at: ?string, watched_at: ?string}}  $facts
     */
    public function handle(User $user, ?Movie $movie, UserTitleAction $action, array $facts): bool
    {
        if ($movie === null) {
            return $facts['after'] === UserMovie::NO_FACTS;
        }

        $unchangedSinceAction = UserMovie::restore($user, $movie, expected: $facts['after'], restored: $facts['before']);

        if ($unchangedSinceAction && $facts['before'] !== $facts['after']) {
            $this->recordEvent->handle($user, AnalyticsEvent::UserMovieActionUndone, ['movie_id' => $movie->id, 'action' => $action->value]);
        }

        return $unchangedSinceAction;
    }
}
