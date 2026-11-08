<?php

namespace App\Http\Support;

use App\Actions\AddToWatchlist;
use App\Actions\ClearWatched;
use App\Actions\MarkAsWatched;
use App\Actions\RemoveFromWatchlist;
use App\Enums\Surface;
use App\Enums\UserMovieAction;
use App\Models\Movie;
use App\Models\User;
use App\Models\UserMovie;
use Illuminate\Http\Request;

/**
 * One Watchlist or Watched action taken for a user, by catalog id, and remembers it as their latest so its toast
 * can undo it. Used by the action endpoints and, after sign-in, for a guest's pending action, so both behave alike.
 */
class UserMovieActionByCatalogId
{
    public function __construct(
        private MovieByCatalogId $movies,
        private AddToWatchlist $addToWatchlist,
        private RemoveFromWatchlist $removeFromWatchlist,
        private MarkAsWatched $markAsWatched,
        private ClearWatched $clearWatched,
    ) {}

    /**
     * Setting a fact finds or creates the Movie, and a Removed Movie is a 410: facts about it can only be cleared,
     * never set. Clearing a catalog id with no Movie row changes nothing and creates no row, since it holds no facts.
     * Returns the facts before and after.
     *
     * @return array{before: array{watchlisted_at: ?string, watched_at: ?string}, after: array{watchlisted_at: ?string, watched_at: ?string}}
     */
    public function apply(Request $request, User $user, string $tmdbId, UserMovieAction $action, Surface $source): array
    {
        $facts = match ($action) {
            UserMovieAction::AddToWatchlist => $this->addToWatchlist->handle($user, $this->movies->findOrCreateUnremoved($request, $tmdbId), $source),
            UserMovieAction::MarkAsWatched => $this->markAsWatched->handle($user, $this->movies->findOrCreateUnremoved($request, $tmdbId), $source),
            UserMovieAction::RemoveFromWatchlist => $this->clear($tmdbId, fn (Movie $movie): array => $this->removeFromWatchlist->handle($user, $movie, $source)),
            UserMovieAction::ClearWatched => $this->clear($tmdbId, fn (Movie $movie): array => $this->clearWatched->handle($user, $movie, $source)),
        };

        LatestUserMovieAction::remember($request, $user, $this->movies->catalogId($tmdbId), $action, $facts);

        return $facts;
    }

    /**
     * @param  callable(Movie): array{before: array{watchlisted_at: ?string, watched_at: ?string}, after: array{watchlisted_at: ?string, watched_at: ?string}}  $clear
     * @return array{before: array{watchlisted_at: ?string, watched_at: ?string}, after: array{watchlisted_at: ?string, watched_at: ?string}}
     */
    private function clear(string $tmdbId, callable $clear): array
    {
        $movie = $this->movies->find($tmdbId);

        return $movie === null ? UserMovie::NO_CHANGE : $clear($movie);
    }
}
