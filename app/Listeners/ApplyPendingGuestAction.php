<?php

namespace App\Listeners;

use App\Actions\ChangeBulkWatched;
use App\Actions\ChangeEpisodeWatched;
use App\Actions\ChangeTvShowWatchlist;
use App\Http\Support\PendingGuestAction;
use App\Http\Support\UserMovieActionByCatalogId;
use App\Models\Episode;
use App\Models\Season;
use App\Models\TvShow;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * On sign-in or registration (which signs the new user in), applies the guest's pending action for them, before
 * any email verification, and flashes it so the page they return to shows its usual toast with Undo. If the action
 * can't be applied, say the Movie is unknown, Removed or the source is down, sign-in still succeeds and an error
 * toast explains; a failure that isn't an HTTP error is reported too. A sign-in on a GET, by a remember-me cookie, applies nothing, so no GET changes data.
 */
class ApplyPendingGuestAction
{
    private const FAILURE_MESSAGE = "Couldn't save that. Try again.";

    public function __construct(private Request $request, private UserMovieActionByCatalogId $actions, private ChangeEpisodeWatched $episodes, private ChangeTvShowWatchlist $tvWatchlist, private ChangeBulkWatched $bulk) {}

    public function handle(Login $event): void
    {
        if (! $event->user instanceof User || ! $this->request->hasSession() || $this->request->isMethod('GET')) {
            return;
        }

        $pending = PendingGuestAction::pull($this->request);

        if ($pending === null) {
            return;
        }

        try {
            if (isset($pending['tv_show_tmdb_id'])) {
                $result = $this->tvWatchlist->handle($this->request, $event->user, (string) $pending['tv_show_tmdb_id'], $pending['watchlisted']);
                Inertia::flash('tvWatchlistAction', ['tmdbId' => $pending['tv_show_tmdb_id'], 'undoToken' => $result['undoToken'], 'watchlisted' => $result['watchlisted']]);

                return;
            }
            if (isset($pending['season_id'])) {
                $season = Season::query()->findOrFail($pending['season_id']);
                $tvShow = TvShow::query()->findOrFail($season->title_id);
                $result = $this->bulk->season($this->request, $event->user, $tvShow, $season, $pending['watched']);
                Inertia::flash('episodeAction', ['tmdbId' => $tvShow->tmdb_id, 'undoToken' => $result['undoToken'], 'message' => $pending['watched'] ? 'Season marked as watched' : 'Season cleared']);

                return;
            }
            if (isset($pending['bulk_tv_show_tmdb_id'])) {
                $result = $this->bulk->tvShow($this->request, $event->user, (string) $pending['bulk_tv_show_tmdb_id'], $pending['watched']);
                Inertia::flash('episodeAction', ['tmdbId' => $pending['bulk_tv_show_tmdb_id'], 'undoToken' => $result['undoToken'], 'message' => $pending['watched'] ? 'TV show marked as watched' : 'TV show cleared']);

                return;
            }
            if (isset($pending['episode_id'])) {
                $episode = Episode::query()->findOrFail($pending['episode_id']);
                $tvShow = TvShow::query()->findOrFail($episode->title_id);
                $result = $this->episodes->handle($this->request, $event->user, $tvShow, $episode, $pending['watched']);
                Inertia::flash('episodeAction', ['tmdbId' => $tvShow->tmdb_id, 'undoToken' => $result['undoToken']]);

                return;
            }
            $facts = $this->actions->apply($this->request, $event->user, (string) $pending['tmdb_id'], $pending['action'], $pending['source']);
        } catch (Throwable $exception) {
            if (! $exception instanceof HttpExceptionInterface) {
                report($exception);
            }

            Inertia::flash('toast', ['type' => 'error', 'message' => self::FAILURE_MESSAGE]);

            return;
        }

        Inertia::flash('userMovieAction', [
            'tmdbId' => $pending['tmdb_id'],
            'action' => $pending['action']->value,
            'previous' => [
                'watchlisted' => $facts['before']['watchlisted_at'] !== null,
                'watched' => $facts['before']['watched_at'] !== null,
                'watchedDate' => $facts['before']['watched_at'],
            ],
        ]);
    }
}
