<?php

namespace App\Http\Controllers;

use App\Actions\RecordEvent;
use App\Http\Support\SearchPage;
use App\Services\Catalog\Search\BrowseOptions;
use App\Services\Catalog\Search\CatalogSearch;
use App\Services\Catalog\Search\ImdbIdLookup;
use App\Services\Ratings\Enums\RatingSourceName;
use App\Services\Ratings\Ratings;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\ScrollMetadata;
use Symfony\Component\HttpFoundation\Response;

/**
 * Search: title search for everyone, with the query and page in the URL, and Browse while there
 * is no query. Results load a page at a time, merged into those already shown, and `userMovies` merges with
 * them. The page is client-rendered and `noindex`.
 */
class SearchController extends Controller
{
    public function __invoke(Request $request, CatalogSearch $catalogSearch, BrowseOptions $browseOptions, ImdbIdLookup $imdbIds, Ratings $ratings, RecordEvent $recordEvent): Response
    {
        $page = SearchPage::fromRequest($request, $catalogSearch, $browseOptions, $imdbIds, $ratings, $recordEvent);

        if ($request->query('tab') === 'tv' && $page->runtimeBand() !== null) {
            $query = $request->query();
            unset($query['tab']);
            unset($query['q']);

            return redirect()->route('search', $query);
        }

        $response = Inertia::render('Search', [
            'imageBaseUrl' => config('catalog.image_base_url'),
            'query' => $page->query,
            'minimumQueryLength' => config('catalog.search.minimum_query_length'),
            'maximumQueryLength' => config('catalog.search.maximum_query_length'),
            'looksUpImdbRatings' => RatingSourceName::Imdb->isEnabled(),
            'includesTvShows' => (bool) config('catalog.tv_shows.enabled'),
            'tab' => $page->browseKind->value,
            'genres' => fn (): array => $page->genres(),
            'genre' => fn (): ?int => $page->genre(),
            'highlyRated' => fn (): bool => $page->highlyRated(),
            'era' => fn (): ?string => $page->era()?->value,
            'runtimeBand' => fn (): ?string => $page->runtimeBand()?->value,
            'streamingServices' => fn (): array => $page->streamingServices(),
            'selectedStreamingServices' => fn (): array => $page->selectedStreamingServices(),
            'maximumStreamingServices' => config('catalog.search.maximum_streaming_services'),
            'sort' => fn (): string => $page->sort()->value,
            'explicitSort' => fn (): ?string => $page->explicitSort()?->value,
            'results' => Inertia::scroll(fn (): array => $page->results(), metadata: fn (): ScrollMetadata => $page->scrollMetadata())->matchOn('data.key'),
            'totalResults' => fn (): ?int => $page->totalResults(),
            'status' => fn (): string => $page->status(),
            'userMovies' => Inertia::merge(fn (): array => $page->userMovies()),
            'userTvShows' => Inertia::merge(fn (): array => $page->userTvShows()),
        ])->toResponse($request);
        $response->headers->set('X-Robots-Tag', 'noindex');

        return $response;
    }
}
