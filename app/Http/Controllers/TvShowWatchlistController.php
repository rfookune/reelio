<?php

namespace App\Http\Controllers;

use App\Actions\ChangeTvShowWatchlist;
use App\Http\Requests\TvShowWatchlistRequest;
use App\Http\Support\TitleActions\TvShowByCatalogId;
use App\Http\Support\Visitor\IntendedUrl;
use App\Http\Support\Visitor\PendingGuestAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class TvShowWatchlistController extends Controller
{
    public function update(TvShowWatchlistRequest $request, string $tmdbId, ChangeTvShowWatchlist $action, TvShowByCatalogId $tvShows): JsonResponse|RedirectResponse
    {
        return $this->apply($request, $tmdbId, $action, $tvShows, true);
    }

    public function destroy(TvShowWatchlistRequest $request, string $tmdbId, ChangeTvShowWatchlist $action, TvShowByCatalogId $tvShows): JsonResponse|RedirectResponse
    {
        return $this->apply($request, $tmdbId, $action, $tvShows, false);
    }

    private function apply(TvShowWatchlistRequest $request, string $tmdbId, ChangeTvShowWatchlist $action, TvShowByCatalogId $tvShows, bool $watchlisted): JsonResponse|RedirectResponse
    {
        $catalogId = $tvShows->catalogId($tmdbId);
        if ($request->user() === null) {
            PendingGuestAction::storeTvShowWatchlist($request, $catalogId, $watchlisted, $request->source());
            IntendedUrl::setToLastPage($request);

            return $request->expectsJson() ? response()->json(['redirect' => route('login')]) : redirect()->route('login');
        }
        $result = $action->handle($request, $request->user(), $tmdbId, $watchlisted, $request->source());

        return $request->expectsJson() ? response()->json($result) : back();
    }
}
