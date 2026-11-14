<?php

namespace App\Http\Controllers;

use App\Http\Support\TitleActions\LatestTvShowAction;
use App\Http\Support\TitleActions\TvShowByCatalogId;
use App\Services\TvShows\WatchedProgress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TvShowUndoController extends Controller
{
    public function __invoke(Request $request, string $tmdbId, LatestTvShowAction $action, WatchedProgress $progress, TvShowByCatalogId $tvShows): JsonResponse
    {
        abort_unless(config('catalog.tv_shows.enabled'), 404);
        $validated = $request->validate(['token' => ['required', 'string', 'uuid']]);
        $catalogId = $tvShows->catalogId($tmdbId);
        if (! $action->undo($request, $request->user(), $catalogId, $validated['token'])) {
            throw ValidationException::withMessages(['undo' => "That can't be undone any more"]);
        }

        $tvShow = $tvShows->find($tmdbId);

        return response()->json(['progress' => $tvShow === null ? null : $progress->for($tvShow, $request->user()), 'watchlisted' => $tvShow?->isWatchlistedBy($request->user()) ?? false]);
    }
}
