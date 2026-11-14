<?php

namespace App\Http\Controllers;

use App\Enums\UserTitleAction;
use App\Http\Requests\UserMovieActionRequest;
use App\Http\Support\TitleActions\UserMovieActionByCatalogId;
use Illuminate\Http\RedirectResponse;

/**
 * Idempotent set and clear of the signed-in user's Watchlist fact, by catalog id, so a Movie with no row yet can
 * be saved from any list. A guest's request is held as a pending action until sign-in instead.
 */
class WatchlistController extends Controller
{
    /** A Removed Movie can only be taken off the Watchlist, never put on it. */
    public function update(UserMovieActionRequest $request, string $tmdbId, UserMovieActionByCatalogId $actions): RedirectResponse
    {
        $actions->apply($request, $request->user(), $tmdbId, UserTitleAction::AddToWatchlist, $request->source());

        return back();
    }

    /** A catalog id with no Movie row holds no facts, so there is nothing to remove and no row is created. */
    public function destroy(UserMovieActionRequest $request, string $tmdbId, UserMovieActionByCatalogId $actions): RedirectResponse
    {
        $actions->apply($request, $request->user(), $tmdbId, UserTitleAction::RemoveFromWatchlist, $request->source());

        return back();
    }
}
