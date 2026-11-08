<?php

namespace App\Http\Controllers;

use App\Actions\RecordEvent;
use App\Http\Support\SearchPage;
use App\Services\Catalog\Discover\DiscoverBuilder;
use App\Services\Catalog\Search\CatalogSearch;
use App\Services\Catalog\Search\ImdbIdLookup;
use App\Services\Ratings\Enums\RatingSourceName;
use App\Services\Ratings\Ratings;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\ScrollMetadata;
use Symfony\Component\HttpFoundation\Response;

/**
 * Search: title search for everyone, with the query and page in the URL. Results load a page at a time, merged
 * into those already shown, and `userMovies` merges with them. The page is client-rendered and `noindex`.
 */
class SearchController extends Controller
{
    public function __invoke(Request $request, CatalogSearch $catalogSearch, ImdbIdLookup $imdbIds, DiscoverBuilder $discover, Ratings $ratings, RecordEvent $recordEvent): Response
    {
        $page = SearchPage::fromRequest($request, $catalogSearch, $imdbIds, $discover, $ratings, $recordEvent);

        $response = Inertia::render('Search', [
            'imageBaseUrl' => config('catalog.image_base_url'),
            'query' => $page->query,
            'minimumQueryLength' => config('catalog.search.minimum_query_length'),
            'maximumQueryLength' => config('catalog.search.maximum_query_length'),
            'looksUpImdbRatings' => RatingSourceName::Imdb->isEnabled(),
            'results' => Inertia::scroll(fn (): array => $page->results(), metadata: fn (): ScrollMetadata => $page->scrollMetadata())->matchOn('data.tmdbId'),
            'totalResults' => fn (): ?int => $page->totalResults(),
            'status' => fn (): ?string => $page->status(),
            'trending' => fn (): array => $page->trending(),
            'userMovies' => Inertia::merge(fn (): array => $page->userMovies()),
        ])->toResponse($request);
        $response->headers->set('X-Robots-Tag', 'noindex');

        return $response;
    }
}
