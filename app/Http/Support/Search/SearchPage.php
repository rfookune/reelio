<?php

namespace App\Http\Support\Search;

use App\Enums\AnalyticsEvent;
use App\Services\Catalog\Sources\Listings\OriginalLanguage;
use App\Services\Catalog\Sources\Listings\RuntimeBand;
use App\Services\Ratings\Enums\RatingSourceName;
use Inertia\Inertia;
use Inertia\ScrollMetadata;

/**
 * The Search page for one request: a search by Name, or Browse when there is no query, put together from the pieces
 * that each know one part of it. `SearchRequest` says what was asked for, `BrowseSelection` which of it Browse
 * offers, `ResultsPage` the Titles and how the Catalog answered, and `SearchCards` the cards and the viewer's facts.
 * This gives the props the Search page renders, lazy so that a partial reload asks only for what it needs, and the
 * analytics event the response calls for.
 */
class SearchPage
{
    public function __construct(
        public readonly SearchRequest $request,
        private BrowseSelection $selection,
        private ResultsPage $resultsPage,
        private SearchCards $cards,
    ) {}

    /**
     * The props of the Search page.
     *
     * @return array<string, mixed>
     */
    public function props(): array
    {
        return [
            'imageBaseUrl' => config('catalog.image_base_url'),
            'query' => $this->request->query,
            'minimumQueryLength' => config('catalog.search.minimum_query_length'),
            'maximumQueryLength' => config('catalog.search.maximum_query_length'),
            'looksUpImdbRatings' => RatingSourceName::Imdb->isEnabled(),
            'includesTvShows' => (bool) config('catalog.tv_shows.enabled'),
            'tab' => $this->request->kind->value,
            'genres' => fn (): array => $this->selection->genres(),
            'genre' => fn (): ?int => $this->selection->filters()->genreId,
            'highlyRated' => fn (): bool => $this->selection->filters()->highlyRated,
            'era' => fn (): ?string => $this->selection->filters()->era?->value,
            'runtimeBand' => fn (): ?string => $this->selection->filters()->runtimeBand?->value,
            'canHideSeen' => $this->request->canHideSeen(),
            'showSeen' => fn (): bool => $this->request->showsSeen(),
            'languages' => OriginalLanguage::options(),
            'language' => fn (): ?string => $this->selection->filters()->originalLanguage?->value,
            'streamingServices' => fn (): array => $this->selection->streamingServices(),
            'selectedStreamingServices' => fn (): array => $this->selection->filters()->streamingServiceIds,
            'maximumStreamingServices' => config('catalog.search.maximum_streaming_services'),
            'sort' => fn (): string => $this->selection->filters()->sort()->value,
            'explicitSort' => fn (): ?string => $this->selection->filters()->requestedSort?->value,
            'results' => Inertia::scroll(fn (): array => $this->cards->results(), metadata: fn (): ScrollMetadata => $this->resultsPage->scrollMetadata())->matchOn('data.key'),
            'totalResults' => fn (): ?int => $this->resultsPage->totalResults(),
            'status' => fn (): string => $this->resultsPage->status(),
            'userMovies' => Inertia::merge(fn (): array => $this->cards->userMovies()),
            'userTvShows' => Inertia::merge(fn (): array => $this->cards->userTvShows()),
        ];
    }

    /**
     * The runtime band Browse is narrowed to, or null for none, which a runtime asked for on the TV Shows tab is
     * redirected on, since a runtime is for Movies.
     */
    public function runtimeBand(): ?RuntimeBand
    {
        return $this->selection->filters()->runtimeBand;
    }

    /**
     * The event this response calls for, or null: a search's first page of results records `title_searched` with the
     * query's length and the result count, never its text, and Browse's first page records `title_browsed` with its
     * tab, the result count and the names, never the values, of the filters used. Nothing is recorded for a later
     * page, for a request that reads only the cache or leaves the results out, or when the Catalog gave no page.
     *
     * @return array{event: AnalyticsEvent, properties: array<string, bool|int|string|null>}|null
     */
    public function analytics(): ?array
    {
        if ($this->request->page !== 1 || $this->request->cacheMode !== CacheMode::Live || ! $this->request->rendersResults || ! $this->resultsPage->isAvailable()) {
            return null;
        }

        $resultCount = count($this->resultsPage->listings());

        if ($this->request->isSearching()) {
            return ['event' => AnalyticsEvent::TitleSearched, 'properties' => ['query_length' => mb_strlen($this->request->query), 'result_count' => $resultCount]];
        }

        return ['event' => AnalyticsEvent::TitleBrowsed, 'properties' => [
            'tab' => $this->request->kind->value,
            'filters' => implode(',', [...$this->selection->filters()->usedNames(), ...($this->request->showsSeen() ? ['show_seen'] : [])]),
            'result_count' => $resultCount,
        ]];
    }
}
