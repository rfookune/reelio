<?php

namespace App\Http\Controllers;

use App\Actions\ChangeTvShowWatched;
use App\Http\Support\IntendedUrl;
use App\Http\Support\PendingGuestAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TvShowWatchedController extends Controller
{
    public function update(Request $request, string $tmdbId, ChangeTvShowWatched $action): JsonResponse|RedirectResponse
    {
        return $this->apply($request, $tmdbId, $action, true);
    }

    public function destroy(Request $request, string $tmdbId, ChangeTvShowWatched $action): JsonResponse|RedirectResponse
    {
        return $this->apply($request, $tmdbId, $action, false);
    }

    private function apply(Request $request, string $tmdbId, ChangeTvShowWatched $action, bool $watched): JsonResponse|RedirectResponse
    {
        $result = $action->handle($request, $request->user(), $tmdbId, $watched);
        if ($request->user() === null) {
            PendingGuestAction::storeTvShow($request, (int) $tmdbId, $watched);
            IntendedUrl::setToLastPage($request);

            return $request->expectsJson() ? response()->json(['redirect' => route('login')]) : redirect()->route('login');
        }

        return $request->expectsJson() ? response()->json($result) : back();
    }
}
