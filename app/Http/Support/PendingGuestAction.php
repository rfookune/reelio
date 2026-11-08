<?php

namespace App\Http\Support;

use App\Enums\Surface;
use App\Enums\UserMovieAction;
use Illuminate\Http\Request;

/**
 * A Watchlist or Watched action a guest took, held in the session until they sign in or register, when it is
 * applied for them. It never travels in a URL. Only the latest is kept, for 30 minutes, and it is used once.
 */
class PendingGuestAction
{
    private const SESSION_KEY = 'user_movie_actions.pending';

    /** How long, in seconds, a pending action waits for sign-in: the spec's retention for it. */
    private const PENDING_FOR = 30 * 60;

    public static function store(Request $request, int $tmdbId, UserMovieAction $action, Surface $source): void
    {
        $request->session()->put(self::SESSION_KEY, [
            'tmdb_id' => $tmdbId,
            'action' => $action->value,
            'source' => $source->value,
            'stored_at' => now()->getTimestamp(),
        ]);
    }

    public static function storeEpisode(Request $request, int $episodeId, bool $watched): void
    {
        $request->session()->put(self::SESSION_KEY, ['episode_id' => $episodeId, 'watched' => $watched, 'stored_at' => now()->timestamp]);
    }

    public static function storeSeason(Request $request, int $seasonId, bool $watched): void
    {
        $request->session()->put(self::SESSION_KEY, ['season_id' => $seasonId, 'watched' => $watched, 'stored_at' => now()->timestamp]);
    }

    public static function storeTvShow(Request $request, int $tmdbId, bool $watched, Surface $source): void
    {
        $request->session()->put(self::SESSION_KEY, ['bulk_tv_show_tmdb_id' => $tmdbId, 'watched' => $watched, 'source' => $source->value, 'stored_at' => now()->timestamp]);
    }

    public static function storeTvShowWatchlist(Request $request, int $tmdbId, bool $watchlisted, Surface $source): void
    {
        $request->session()->put(self::SESSION_KEY, ['tv_show_tmdb_id' => $tmdbId, 'watchlisted' => $watchlisted, 'source' => $source->value, 'stored_at' => now()->timestamp]);
    }

    /**
     * Takes the pending action out of the session, so it is applied at most once, if it has not expired.
     *
     * @return array{tmdb_id: int, action: UserMovieAction, source: Surface}|array{episode_id: int, watched: bool}|array{season_id: int, watched: bool}|array{bulk_tv_show_tmdb_id: int, watched: bool, source: Surface}|array{tv_show_tmdb_id: int, watchlisted: bool, source: Surface}|null
     */
    public static function pull(Request $request): ?array
    {
        $pending = $request->session()->pull(self::SESSION_KEY);

        if (! is_array($pending) || now()->getTimestamp() - $pending['stored_at'] > self::PENDING_FOR) {
            return null;
        }

        if (isset($pending['tv_show_tmdb_id'])) {
            return ['tv_show_tmdb_id' => $pending['tv_show_tmdb_id'], 'watchlisted' => $pending['watchlisted'], 'source' => Surface::from($pending['source'])];
        }

        if (isset($pending['season_id'])) {
            return ['season_id' => $pending['season_id'], 'watched' => $pending['watched']];
        }

        if (isset($pending['bulk_tv_show_tmdb_id'])) {
            return ['bulk_tv_show_tmdb_id' => $pending['bulk_tv_show_tmdb_id'], 'watched' => $pending['watched'], 'source' => Surface::from($pending['source'])];
        }

        if (isset($pending['episode_id'])) {
            return ['episode_id' => $pending['episode_id'], 'watched' => $pending['watched']];
        }

        return [
            'tmdb_id' => $pending['tmdb_id'],
            'action' => UserMovieAction::from($pending['action']),
            'source' => Surface::from($pending['source']),
        ];
    }
}
