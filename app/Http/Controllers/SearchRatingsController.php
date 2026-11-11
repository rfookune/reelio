<?php

namespace App\Http\Controllers;

use App\Actions\RecordEvent;
use App\Http\Support\SearchPage;
use App\Services\Catalog\Search\BrowseGenres;
use App\Services\Catalog\Search\CatalogSearch;
use App\Services\Catalog\Search\ImdbIdLookup;
use App\Services\Ratings\Ratings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The IMDb ratings the Search page's cards couldn't show at first, for one page of results. A search result carries
 * no IMDb id, and neither does a Browse card, so the page asks for these after it renders, once for each page it
 * shows. The page is named by its query, or its tab, genre and sort when there is no query, and its number, never by ids,
 * and read from the cache alone, so this is no way to look up an arbitrary Movie or to search. It records no
 * analytics event.
 */
class SearchRatingsController extends Controller
{
    public function __invoke(Request $request, CatalogSearch $catalogSearch, BrowseGenres $browseGenres, ImdbIdLookup $imdbIds, Ratings $ratings, RecordEvent $recordEvent): JsonResponse
    {
        $page = SearchPage::cachedFromRequest($request, $catalogSearch, $browseGenres, $imdbIds, $ratings, $recordEvent);

        return response()->json(['ratings' => (object) $page->lookedUpRatings()])->header('X-Robots-Tag', 'noindex');
    }
}
