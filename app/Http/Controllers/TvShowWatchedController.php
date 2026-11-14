<?php

namespace App\Http\Controllers;

use App\Actions\ChangeTvShowWatched;
use App\Enums\Surface;
use App\Http\Support\Visitor\IntendedUrl;
use App\Http\Support\Visitor\PendingGuestAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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
        $validated = $request->validate(['source' => ['sometimes', 'required', 'string', Rule::enum(Surface::class)->only(Surface::tvShowActionSources())]]);
        $source = Surface::from($validated['source'] ?? Surface::TvShow->value);
        $result = $action->handle($request, $request->user(), $tmdbId, $watched, $source);
        if ($request->user() === null) {
            PendingGuestAction::storeTvShow($request, (int) $tmdbId, $watched, $source);
            IntendedUrl::setToLastPage($request);

            return $request->expectsJson() ? response()->json(['redirect' => route('login')]) : redirect()->route('login');
        }

        return $request->expectsJson() ? response()->json($result) : back();
    }
}
