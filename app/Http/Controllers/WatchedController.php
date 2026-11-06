<?php

namespace App\Http\Controllers;

use App\Actions\ClearWatched;
use App\Actions\MarkAsWatched;
use App\Enums\UserMovieAction;
use App\Http\Requests\UserMovieActionRequest;
use App\Http\Support\LatestUserMovieAction;
use App\Http\Support\MovieByCatalogId;
use App\Models\UserMovie;
use Illuminate\Http\RedirectResponse;

/**
 * Idempotent mark and clear of the signed-in user's Watched fact, by catalog id, so a Movie with no row yet can be
 * marked as watched from any list.
 */
class WatchedController extends Controller
{
    /** A Removed Movie's Watched record can only be cleared, never set. */
    public function update(UserMovieActionRequest $request, string $tmdbId, MovieByCatalogId $movies, MarkAsWatched $markAsWatched): RedirectResponse
    {
        $facts = $markAsWatched->handle($request->user(), $movies->findOrCreateUnremoved($request, $tmdbId), $request->source());
        LatestUserMovieAction::remember($request, $movies->catalogId($tmdbId), UserMovieAction::MarkAsWatched, $facts);

        return back();
    }

    /** A catalog id with no Movie row holds no facts, so there is nothing to clear and no row is created. */
    public function destroy(UserMovieActionRequest $request, string $tmdbId, MovieByCatalogId $movies, ClearWatched $clearWatched): RedirectResponse
    {
        $movie = $movies->find($tmdbId);
        $facts = $movie === null ? UserMovie::NO_CHANGE : $clearWatched->handle($request->user(), $movie, $request->source());
        LatestUserMovieAction::remember($request, $movies->catalogId($tmdbId), UserMovieAction::ClearWatched, $facts);

        return back();
    }
}
