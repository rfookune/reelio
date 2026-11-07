<?php

namespace App\Http\Controllers;

use App\Actions\RecordEvent;
use App\Http\Support\SearchPage;
use App\Services\Catalog\Catalog;
use App\Services\Ratings\Ratings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The IMDb ratings the Search page's cards couldn't show at first, for one page of results. A search result carries
 * no IMDb id, so the page asks for these after it renders, once for each page it shows. The page is named by its
 * query and number, never by ids, and read from the cache alone, so this is no way to look up an arbitrary Movie or
 * to search. It records no analytics event.
 */
class SearchRatingsController extends Controller
{
    public function __invoke(Request $request, Catalog $catalog, Ratings $ratings, RecordEvent $recordEvent): JsonResponse
    {
        $page = SearchPage::cachedFromRequest($request, $catalog, $ratings, $recordEvent);

        return response()->json(['ratings' => (object) ($page->isSearching() ? $page->lookedUpRatings() : [])])->header('X-Robots-Tag', 'noindex');
    }
}
