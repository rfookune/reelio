<?php

namespace App\Http\Controllers;

use App\Enums\ListFilter;
use App\Enums\ListSort;
use App\Enums\UserList;
use App\Http\Support\UserListPage;
use App\Services\Catalog\Discover\DiscoverBuilder;
use App\Services\Ratings\Ratings;
use App\Services\TvShows\ProgressSummaries;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\ScrollMetadata;
use Symfony\Component\HttpFoundation\Response;

class UserListController extends Controller
{
    public function __invoke(Request $request, DiscoverBuilder $discover, Ratings $ratings, ProgressSummaries $progress): Response
    {
        $list = $request->routeIs('watched') ? UserList::Watched : UserList::Watchlist;
        $requested = $request->query('sort');
        $sort = $requested === null ? $list->defaultSort() : (is_string($requested) ? ListSort::tryFrom($requested) : null);
        abort_unless($sort !== null && in_array($sort, $list->sorts(), true), 422, 'Unknown '.$list->name.' sort.');
        $requestedFilter = $request->query('filter');
        $filter = $requestedFilter === null ? ListFilter::All : (is_string($requestedFilter) ? ListFilter::tryFrom($requestedFilter) : null);
        abort_unless($filter !== null, 422, 'Unknown '.$list->name.' filter.');
        $page = new UserListPage($request->user(), $list, $sort, $filter, $discover, $ratings, $progress);

        if ($request->has('restore')) {
            $tmdbId = filter_var($request->query('restore'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            abort_unless($tmdbId !== false, 422, 'Invalid Movie.');

            return redirect($page->restoredMovieUrl($tmdbId));
        }

        $response = Inertia::render($list->name, [
            'imageBaseUrl' => config('catalog.image_base_url'),
            'sort' => $sort->value,
            'filter' => $filter->value,
            'unavailableTitles' => fn (): array => $page->unavailableTitles(),
            'unavailableMovies' => fn (): array => $page->unavailableMovies(),
            'titles' => Inertia::scroll(fn (): array => $page->titles(), metadata: fn (): ScrollMetadata => $page->scrollMetadata())->matchOn('data.key'),
            'trending' => fn (): array => $page->trending(),
            'userMovies' => fn (): array => $page->userMovies(),
            'userTvShows' => fn (): array => $page->userTvShows(),
        ])->toResponse($request);
        $response->headers->set('X-Robots-Tag', 'noindex');

        return $response;
    }
}
