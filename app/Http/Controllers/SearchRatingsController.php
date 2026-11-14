<?php

namespace App\Http\Controllers;

use App\Http\Support\Search\SearchPages;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The IMDb ratings the Search page's cards couldn't show at first, for one page of results. A search result carries
 * no IMDb id, and neither does a Browse card, so the page asks for these after it renders, once for each page it
 * shows. The page is named by its query, or its tab, genre, Streaming services and sort when there is no query, and its number, never by ids,
 * and read from the cache alone, so this is no way to look up an arbitrary Movie or to search. It records no
 * analytics event.
 */
class SearchRatingsController extends Controller
{
    public function __invoke(Request $request, SearchPages $searchPages): JsonResponse
    {
        return response()->json(['ratings' => (object) $searchPages->ratingsLookupFor($request)->lookedUp()])->header('X-Robots-Tag', 'noindex');
    }
}
