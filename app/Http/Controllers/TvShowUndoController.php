<?php

namespace App\Http\Controllers;

use App\Actions\ChangeEpisodeWatched;
use App\Models\TvShow;
use App\Services\TvShows\WatchedProgress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TvShowUndoController extends Controller
{
    public function __invoke(Request $request, string $tmdbId, ChangeEpisodeWatched $action, WatchedProgress $progress): JsonResponse
    {
        abort_unless(config('catalog.tv_shows.enabled'), 404);
        $validated = $request->validate(['token' => ['required', 'string', 'uuid']]);
        $tvShow = TvShow::query()->where('tmdb_id', $tmdbId)->firstOrFail();
        if (! $action->undo($request, $request->user(), $tvShow, $validated['token'])) {
            throw ValidationException::withMessages(['undo' => "That can't be undone any more"]);
        }

        return response()->json(['progress' => $progress->for($tvShow, $request->user())]);
    }
}
