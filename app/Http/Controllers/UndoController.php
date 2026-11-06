<?php

namespace App\Http\Controllers;

use App\Actions\UndoUserMovieAction;
use App\Http\Requests\UndoUserMovieActionRequest;
use App\Http\Support\LatestUserMovieAction;
use App\Http\Support\MovieByCatalogId;
use App\Models\UserMovie;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

/**
 * Undo of the signed-in user's latest Watchlist or Watched action, by catalog id, from its toast.
 */
class UndoController extends Controller
{
    private const CANNOT_UNDO = "That can't be undone any more";

    /**
     * Restores the Movie's facts from before the action, if that action is still the latest and nothing has changed
     * since; otherwise "That can't be undone any more". A Movie row purged since the action left it without facts
     * is created again through the Catalog, under the creation limit, like any other row. A Removed Movie's facts
     * are restored too: Undo puts back what was there rather than setting a new fact. The action is forgotten once
     * its Undo is tried, but not when finding the Movie fails, so Undo can be tried again, nor by an older toast's
     * Undo, which leaves the latest one undoable.
     */
    public function __invoke(UndoUserMovieActionRequest $request, string $tmdbId, MovieByCatalogId $movies, UndoUserMovieAction $undo): RedirectResponse
    {
        $facts = LatestUserMovieAction::find($request, $movies->catalogId($tmdbId), $request->action());

        if ($facts === null) {
            throw ValidationException::withMessages(['undo' => self::CANNOT_UNDO]);
        }

        $movie = $movies->find($tmdbId);

        if ($movie === null && $facts['after'] === UserMovie::NO_FACTS && $facts['before'] !== UserMovie::NO_FACTS) {
            $movie = $movies->findOrCreate($request, $tmdbId);
        }

        LatestUserMovieAction::forget($request);

        if (! $undo->handle($request->user(), $movie, $request->action(), $facts)) {
            throw ValidationException::withMessages(['undo' => self::CANNOT_UNDO]);
        }

        return back();
    }
}
