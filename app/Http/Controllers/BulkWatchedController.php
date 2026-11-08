<?php

namespace App\Http\Controllers;

use App\Actions\ChangeBulkWatched;
use App\Http\Support\IntendedUrl;
use App\Http\Support\PendingGuestAction;
use App\Http\Support\TvShowByCatalogId;
use App\Models\Season;
use App\Models\TvShow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BulkWatchedController extends Controller
{
    public function updateSeason(Request $request, TvShow $tvShow, Season $season, ChangeBulkWatched $action): JsonResponse|RedirectResponse
    {
        return $this->season($request, $tvShow, $season, $action, true);
    }

    public function destroySeason(Request $request, TvShow $tvShow, Season $season, ChangeBulkWatched $action): JsonResponse|RedirectResponse
    {
        return $this->season($request, $tvShow, $season, $action, false);
    }

    public function updateTvShow(Request $request, string $tmdbId, ChangeBulkWatched $action, TvShowByCatalogId $tvShows): JsonResponse|RedirectResponse
    {
        return $this->tvShow($request, $tmdbId, $action, $tvShows, true);
    }

    public function destroyTvShow(Request $request, string $tmdbId, ChangeBulkWatched $action, TvShowByCatalogId $tvShows): JsonResponse|RedirectResponse
    {
        return $this->tvShow($request, $tmdbId, $action, $tvShows, false);
    }

    private function season(Request $request, TvShow $tvShow, Season $season, ChangeBulkWatched $action, bool $watched): JsonResponse|RedirectResponse
    {
        if ($request->user() === null) {
            $action->validateSeason($tvShow, $season);
            PendingGuestAction::storeSeason($request, $season->id, $watched);

            return $this->toLogin($request);
        }

        return $this->respond($request, $action->season($request, $request->user(), $tvShow, $season, $watched));
    }

    private function tvShow(Request $request, string $tmdbId, ChangeBulkWatched $action, TvShowByCatalogId $tvShows, bool $watched): JsonResponse|RedirectResponse
    {
        $catalogId = $tvShows->catalogId($tmdbId);
        if ($request->user() === null) {
            if ($watched) {
                $action->validateTvShowMark($tmdbId);
            }
            PendingGuestAction::storeTvShow($request, $catalogId, $watched);

            return $this->toLogin($request);
        }

        return $this->respond($request, $action->tvShow($request, $request->user(), $tmdbId, $watched));
    }

    private function toLogin(Request $request): JsonResponse|RedirectResponse
    {
        IntendedUrl::setToLastPage($request);

        return $request->expectsJson() ? response()->json(['redirect' => route('login')]) : redirect()->route('login');
    }

    /** @param array<string, mixed> $result */
    private function respond(Request $request, array $result): JsonResponse|RedirectResponse
    {
        return $request->expectsJson() ? response()->json($result) : back();
    }
}
