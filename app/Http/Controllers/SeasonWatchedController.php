<?php

namespace App\Http\Controllers;

use App\Actions\ChangeSeasonWatched;
use App\Http\Support\Visitor\IntendedUrl;
use App\Http\Support\Visitor\PendingGuestAction;
use App\Models\Season;
use App\Models\TvShow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SeasonWatchedController extends Controller
{
    public function update(Request $request, TvShow $tvShow, Season $season, ChangeSeasonWatched $action): JsonResponse|RedirectResponse
    {
        return $this->apply($request, $tvShow, $season, $action, true);
    }

    public function destroy(Request $request, TvShow $tvShow, Season $season, ChangeSeasonWatched $action): JsonResponse|RedirectResponse
    {
        return $this->apply($request, $tvShow, $season, $action, false);
    }

    private function apply(Request $request, TvShow $tvShow, Season $season, ChangeSeasonWatched $action, bool $watched): JsonResponse|RedirectResponse
    {
        $result = $action->handle($request, $request->user(), $tvShow, $season, $watched);
        if ($request->user() === null) {
            PendingGuestAction::storeSeason($request, $season->id, $watched);
            IntendedUrl::setToLastPage($request);

            return $request->expectsJson() ? response()->json(['redirect' => route('login')]) : redirect()->route('login');
        }

        return $request->expectsJson() ? response()->json($result) : back();
    }
}
