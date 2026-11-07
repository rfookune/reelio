<?php

namespace App\Http\Controllers;

use App\Enums\UserMovieAction;
use App\Http\Requests\UserMovieActionRequest;
use App\Http\Support\UserMovieActionByCatalogId;
use App\Http\Support\WatchlistPage;
use App\Services\Catalog\Catalog;
use App\Services\Ratings\Ratings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Idempotent set and clear of the signed-in user's Watchlist fact, by catalog id, so a Movie with no row yet can
 * be saved from any list. A guest's request is held as a pending action until sign-in instead.
 */
class WatchlistController extends Controller
{
    public function index(Request $request, Catalog $catalog, Ratings $ratings): Response
    {
        $sort = $request->query('sort', 'recently-added');
        abort_unless(is_string($sort) && in_array($sort, ['recently-added', 'oldest-added', 'title', 'newest-release', 'oldest-release'], true), 422, 'Unknown Watchlist sort.');
        $page = new WatchlistPage($request->user(), $sort, $catalog, $ratings);

        if ($request->has('restore')) {
            $tmdbId = filter_var($request->query('restore'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            abort_unless($tmdbId !== false, 422, 'Invalid Movie.');

            return redirect($page->restoredMovieUrl($tmdbId));
        }

        $response = Inertia::render('Watchlist', [
            'imageBaseUrl' => config('catalog.image_base_url'),
            'sort' => $sort,
            'movies' => Inertia::scroll(fn (): array => $page->movies(), metadata: fn () => $page->scrollMetadata())->matchOn('data.tmdbId'),
            'trending' => fn (): array => $page->trending(),
            'userMovies' => fn (): array => $page->userMovies(),
        ])->toResponse($request);
        $response->headers->set('X-Robots-Tag', 'noindex');

        return $response;
    }

    /** A Removed Movie can only be taken off the Watchlist, never put on it. */
    public function update(UserMovieActionRequest $request, string $tmdbId, UserMovieActionByCatalogId $actions): RedirectResponse
    {
        $actions->apply($request, $request->user(), $tmdbId, UserMovieAction::AddToWatchlist, $request->source());

        return back();
    }

    /** A catalog id with no Movie row holds no facts, so there is nothing to remove and no row is created. */
    public function destroy(UserMovieActionRequest $request, string $tmdbId, UserMovieActionByCatalogId $actions): RedirectResponse
    {
        $actions->apply($request, $request->user(), $tmdbId, UserMovieAction::RemoveFromWatchlist, $request->source());

        return back();
    }
}
