<?php

namespace App\Http\Support\Search;

use App\Services\Catalog\Search\BrowseOptions;
use App\Services\Catalog\Search\CatalogSearch;
use App\Services\Catalog\Search\ImdbIdLookup;
use App\Services\Ratings\Ratings;
use Illuminate\Http\Request;

/**
 * Builds what the Search routes need from a request, so a controller asks for one thing and the collaborators the
 * pieces share are listed once: the page for the Search route, and the lookup of IMDb ratings for its second route.
 */
class SearchPages
{
    public function __construct(
        private CatalogSearch $catalogSearch,
        private BrowseOptions $browseOptions,
        private UnseenBrowse $unseenBrowse,
        private ImdbIdLookup $imdbIds,
        private Ratings $ratings,
    ) {}

    /** The page for a request, which reads the Catalog live unless it is a reload of `userMovies` alone. */
    public function forRequest(Request $request): SearchPage
    {
        $searchRequest = SearchRequest::from($request);
        [$selection, $resultsPage, $titles] = $this->pipelineFor($searchRequest);

        return new SearchPage($searchRequest, $selection, $resultsPage, new SearchCards($searchRequest, $titles, $this->imdbIds, $this->ratings));
    }

    /**
     * The lookup of the IMDb ratings a page's cards couldn't show: the request's query and page, read as the page reads
     * them, from the cache alone. It searches nothing and records nothing.
     */
    public function ratingsLookupFor(Request $request): RatingsLookup
    {
        [, , $titles] = $this->pipelineFor(SearchRequest::from($request, CacheMode::CachedPage));

        return new RatingsLookup($titles, $this->imdbIds, $this->ratings);
    }

    /** @return array{BrowseSelection, ResultsPage, ListedTitles} */
    private function pipelineFor(SearchRequest $request): array
    {
        $selection = new BrowseSelection($request, $this->browseOptions);
        $resultsPage = new ResultsPage($request, $selection, $this->catalogSearch, $this->unseenBrowse);

        return [$selection, $resultsPage, new ListedTitles($resultsPage)];
    }
}
