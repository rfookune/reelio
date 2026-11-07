<?php

namespace App\Http\Controllers;

use App\Enums\UserMovieList;
use App\Http\Support\UserMovieListPage;
use App\Services\Catalog\Catalog;
use App\Services\Ratings\Ratings;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\ScrollMetadata;
use Symfony\Component\HttpFoundation\Response;

class UserMovieListController extends Controller
{
    public function __invoke(Request $request, Catalog $catalog, Ratings $ratings): Response
    {
        $list = $request->routeIs('watched') ? UserMovieList::Watched : UserMovieList::Watchlist;
        $sort = $request->query('sort', $list->defaultSort());
        abort_unless(is_string($sort) && in_array($sort, $list->sorts(), true), 422, 'Unknown '.$list->name.' sort.');
        $page = new UserMovieListPage($request->user(), $list, $sort, $catalog, $ratings);

        if ($request->has('restore')) {
            $tmdbId = filter_var($request->query('restore'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            abort_unless($tmdbId !== false, 422, 'Invalid Movie.');

            return redirect($page->restoredMovieUrl($tmdbId));
        }

        $response = Inertia::render($list->name, [
            'imageBaseUrl' => config('catalog.image_base_url'),
            'sort' => $sort,
            'movies' => Inertia::scroll(fn (): array => $page->movies(), metadata: fn (): ScrollMetadata => $page->scrollMetadata())->matchOn('data.tmdbId'),
            'trending' => fn (): array => $page->trending(),
            'userMovies' => fn (): array => $page->userMovies(),
        ])->toResponse($request);
        $response->headers->set('X-Robots-Tag', 'noindex');

        return $response;
    }
}
