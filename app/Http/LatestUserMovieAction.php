<?php

namespace App\Http;

use App\Enums\UserMovieAction;
use Illuminate\Http\Request;

/**
 * The user's latest Watchlist or Watched action, kept in the session so its toast can undo it: the Movie's facts
 * before and after, by catalog id, so Undo restores dates the server recorded and never ones the client sends.
 * Only the latest is kept, for 30 minutes. Every action that answers with a toast is remembered, even one that
 * changed nothing, so an older action's Undo can't outlive it.
 */
class LatestUserMovieAction
{
    private const SESSION_KEY = 'user_movie_actions.latest';

    /** How long, in seconds, an action stays undoable: the spec's retention for the Undo snapshot. */
    private const UNDOABLE_FOR = 30 * 60;

    /** @param  array{before: array{watchlisted_at: ?string, watched_at: ?string}, after: array{watchlisted_at: ?string, watched_at: ?string}}  $facts */
    public static function remember(Request $request, int $tmdbId, UserMovieAction $action, array $facts): void
    {
        $request->session()->put(self::SESSION_KEY, [
            'user_id' => $request->user()->id,
            'tmdb_id' => $tmdbId,
            'action' => $action->value,
            'before' => $facts['before'],
            'after' => $facts['after'],
            'remembered_at' => now()->getTimestamp(),
        ]);
    }

    /**
     * The facts before and after the latest action, if it is the one named (the same user, Movie and action) and
     * is still undoable.
     *
     * @return array{before: array{watchlisted_at: ?string, watched_at: ?string}, after: array{watchlisted_at: ?string, watched_at: ?string}}|null
     */
    public static function find(Request $request, int $tmdbId, UserMovieAction $action): ?array
    {
        $latest = $request->session()->get(self::SESSION_KEY);

        if (! is_array($latest)
            || $latest['user_id'] !== $request->user()->id
            || $latest['tmdb_id'] !== $tmdbId
            || $latest['action'] !== $action->value
            || now()->getTimestamp() - $latest['remembered_at'] > self::UNDOABLE_FOR) {
            return null;
        }

        return ['before' => $latest['before'], 'after' => $latest['after']];
    }

    /** Forgets the latest action, so it is undone at most once. */
    public static function forget(Request $request): void
    {
        $request->session()->forget(self::SESSION_KEY);
    }
}
