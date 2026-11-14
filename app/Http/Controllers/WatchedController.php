<?php

namespace App\Http\Controllers;

use App\Enums\UserTitleAction;
use App\Http\Requests\UserMovieActionRequest;
use App\Http\Support\TitleActions\UserMovieActionByCatalogId;
use Illuminate\Http\RedirectResponse;

/**
 * Idempotent mark and clear of the signed-in user's Watched fact, by catalog id, so a Movie with no row yet can be
 * marked as watched from any list. A guest's request is held as a pending action until sign-in instead.
 */
class WatchedController extends Controller
{
    /** A Removed Movie's Watched record can only be cleared, never set. */
    public function update(UserMovieActionRequest $request, string $tmdbId, UserMovieActionByCatalogId $actions): RedirectResponse
    {
        $actions->apply($request, $request->user(), $tmdbId, UserTitleAction::MarkAsWatched, $request->source());

        return back();
    }

    /** A catalog id with no Movie row holds no facts, so there is nothing to clear and no row is created. */
    public function destroy(UserMovieActionRequest $request, string $tmdbId, UserMovieActionByCatalogId $actions): RedirectResponse
    {
        $actions->apply($request, $request->user(), $tmdbId, UserTitleAction::ClearWatched, $request->source());

        return back();
    }
}
