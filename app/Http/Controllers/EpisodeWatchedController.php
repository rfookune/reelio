<?php

namespace App\Http\Controllers;

use App\Actions\ChangeEpisodeWatched;
use App\Http\Support\IntendedUrl;
use App\Http\Support\PendingGuestAction;
use App\Models\Episode;
use App\Models\Season;
use App\Models\TvShow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EpisodeWatchedController extends Controller
{
    public function update(Request $request, TvShow $tvShow, Season $season, Episode $episode, ChangeEpisodeWatched $action): JsonResponse|RedirectResponse
    {
        return $this->apply($request, $tvShow, $episode, $action, true);
    }

    public function destroy(Request $request, TvShow $tvShow, Season $season, Episode $episode, ChangeEpisodeWatched $action): JsonResponse|RedirectResponse
    {
        return $this->apply($request, $tvShow, $episode, $action, false);
    }

    private function apply(Request $request, TvShow $tvShow, Episode $episode, ChangeEpisodeWatched $action, bool $watched): JsonResponse|RedirectResponse
    {
        $result = $action->handle($request, $request->user(), $tvShow, $episode, $watched);
        if ($request->user() === null) {
            PendingGuestAction::storeEpisode($request, $episode->id, $watched);
            IntendedUrl::setToLastPage($request);

            return $request->expectsJson() ? response()->json(['redirect' => route('login')]) : redirect()->route('login');
        }

        return $request->expectsJson() ? response()->json($result) : back();
    }
}
