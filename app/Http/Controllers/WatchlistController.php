<?php

namespace App\Http\Controllers;

use App\Actions\AddToWatchlist;
use App\Actions\RemoveFromWatchlist;
use App\Http\MovieByCatalogId;
use App\Http\Requests\UserMovieActionRequest;
use Illuminate\Http\RedirectResponse;

/**
 * Idempotent set and clear of the signed-in user's Watchlist fact, by catalog id, so a Movie with no row yet can
 * be saved from any list.
 */
class WatchlistController extends Controller
{
    /** A Removed Movie can only be taken off the Watchlist, never put on it. */
    public function update(UserMovieActionRequest $request, string $tmdbId, MovieByCatalogId $movies, AddToWatchlist $addToWatchlist): RedirectResponse
    {
        $addToWatchlist->handle($request->user(), $movies->findOrCreateUnremoved($request, $tmdbId), $request->source());

        return back();
    }

    /** A catalog id with no Movie row holds no facts, so there is nothing to remove and no row is created. */
    public function destroy(UserMovieActionRequest $request, string $tmdbId, MovieByCatalogId $movies, RemoveFromWatchlist $removeFromWatchlist): RedirectResponse
    {
        $movie = $movies->find($tmdbId);

        if ($movie !== null) {
            $removeFromWatchlist->handle($request->user(), $movie, $request->source());
        }

        return back();
    }
}
