<?php

namespace App\Http\Support\Search;

use App\Actions\RecordEvent;
use App\Enums\AnalyticsEvent;
use App\Http\Support\Cards\UserMovies;
use App\Models\Movie;
use App\Services\Catalog\Search\BrowseOptions;
use App\Services\Catalog\Search\CatalogSearch;
use App\Services\Catalog\Search\ImdbIdLookup;
use App\Services\Catalog\Sources\Listings\BrowseSort;
use App\Services\Catalog\Sources\Listings\Era;
use App\Services\Catalog\Sources\Listings\OriginalLanguage;
use App\Services\Catalog\Sources\Listings\RuntimeBand;
use App\Services\Ratings\Ratings;
use Illuminate\Http\Request;
use Inertia\ScrollMetadata;

/**
 * The Search page's props for one response, asking the Catalog for the search, or for Browse when there is none, at
 * most once. A query shorter than `search.minimum_query_length` isn't searched, and the page lists Browse's Popular
 * Movies, or TV Shows when the request's `tab` is `tv` and they are switched on, a page at a time, as it lists a
 * search's results; the tab is ignored in a search by Name. Browse can be narrowed by a genre of the tab's list, the
 * request's `genre`: an id the tab doesn't offer is carried over to the tab's genre of the same name, if it has one,
 * and any other value is ignored, so only an offered id reaches the source. Browse is sorted by the request's `sort` when it
 * is one of the offered values, else by the default of its filters. A search by Name takes no filters or sort. Browse can also be narrowed to Titles on any of the request's `services`, the
 * ids of Streaming services the tab offers in the visitor's country, by subscription or free offer. Each Movie links to its canonical URL when it has a row, else to the entry route,
 * both with the `search` referrer, and shows one External rating: IMDb when its IMDb id is known, from its row or
 * remembered, and rated, else TMDB. The ids not known are looked up afterwards, by `lookedUpRatings()`.
 * Results hold TV Shows beside Movies, each by its kind and catalog id (`key`: the catalog id, or `tv:` and it for a
 * TV Show), linking through its own entry route; with TV Shows off they are dropped. A TV Show has no Movie
 * Watchlist toggle, so `userMovies` covers Movies only and `userTvShows` carries their Watchlist fact alone.
 * A search's results page records `title_searched`, and Browse's first page `title_browsed` with its tab, result count
 * and the names, never the values, of the filters used; a reload of `userMovies` alone records nothing and asks no source.
 */
class SearchPage
{
    public function __construct(
        private BrowseSelection $selection,
        private ResultsPage $resultsPage,
        private SearchCards $cards,
        private RatingsLookup $ratingsLookup,
        private RecordEvent $recordEvent,
        public readonly SearchRequest $request,
    ) {}

    /** The page for a request, which reads the Catalog live unless it is a reload of `userMovies` alone. */
    public static function fromRequest(Request $request, CatalogSearch $catalogSearch, BrowseOptions $browseOptions, UnseenBrowse $unseenBrowse, ImdbIdLookup $imdbIds, Ratings $ratingSources, RecordEvent $recordEvent): self
    {
        return self::assemble(SearchRequest::from($request), $catalogSearch, $browseOptions, $unseenBrowse, $imdbIds, $ratingSources, $recordEvent);
    }

    /**
     * The page asked for by the Search page's lookups of the IMDb ratings: the request's query and page, read as
     * `fromRequest()` reads them, from the cache alone. It searches nothing and records nothing.
     */
    public static function cachedFromRequest(Request $request, CatalogSearch $catalogSearch, BrowseOptions $browseOptions, UnseenBrowse $unseenBrowse, ImdbIdLookup $imdbIds, Ratings $ratingSources, RecordEvent $recordEvent): self
    {
        return self::assemble(SearchRequest::from($request, CacheMode::CachedPage), $catalogSearch, $browseOptions, $unseenBrowse, $imdbIds, $ratingSources, $recordEvent);
    }

    private static function assemble(SearchRequest $request, CatalogSearch $catalogSearch, BrowseOptions $browseOptions, UnseenBrowse $unseenBrowse, ImdbIdLookup $imdbIds, Ratings $ratingSources, RecordEvent $recordEvent): self
    {
        $selection = new BrowseSelection($request, $browseOptions);
        $resultsPage = new ResultsPage($request, $selection, $catalogSearch, $unseenBrowse);
        $titles = new ListedTitles($resultsPage);

        return new self($selection, $resultsPage, new SearchCards($request, $titles, $imdbIds, $ratingSources), new RatingsLookup($titles, $imdbIds, $ratingSources), $recordEvent, $request);
    }

    /**
     * The page's results, a search's or Browse's. A signed-in viewer's first page of a search records `title_searched` with the
     * query's length and the result count, never its text; their first page of Browse records `title_browsed` with the
     * tab, the result count and the names, never the values, of the filters used.
     *
     * @return array{data: list<array{kind: string, key: string, tmdbId: int, title: ?string, year: ?int, posterPath: ?string, ratings: list<array{source: string, name: string, value: float, voteCount: int}>, href: string, seasonCount?: ?int, page: int}>}
     */
    public function results(): array
    {
        $results = $this->resultsPage->isAvailable();

        if ($this->request->isSearching() && $results && $this->request->page === 1) {
            $this->recordEvent->handle($this->request->user, AnalyticsEvent::TitleSearched, ['query_length' => mb_strlen($this->request->query), 'result_count' => count($this->resultsPage->listings())]);
        }

        if (! $this->request->isSearching() && $results && $this->request->page === 1) {
            $this->recordEvent->handle($this->request->user, AnalyticsEvent::TitleBrowsed, [
                'tab' => $this->request->kind->value,
                'filters' => implode(',', [...$this->selection->filters()->usedNames(), ...($this->showSeen() ? ['show_seen'] : [])]),
                'result_count' => count($this->resultsPage->listings()),
            ]);
        }

        return $this->cards->results();
    }

    /**
     * The IMDb ratings the cards couldn't show, once the IMDb ids of the page's Titles are known.
     *
     * @return array<int|string, list<array{source: string, name: string, value: float, voteCount: int}>>
     */
    public function lookedUpRatings(): array
    {
        return $this->ratingsLookup->lookedUp();
    }

    public function scrollMetadata(): ScrollMetadata
    {
        return $this->resultsPage->scrollMetadata();
    }

    public function totalResults(): ?int
    {
        return $this->resultsPage->totalResults();
    }

    /**
     * The genres the tab offers, in the source's order, a search by Name included so that choosing one can leave it;
     * none while the source's list is unavailable.
     *
     * @return list<array{id: int, name: string}>
     */
    public function genres(): array
    {
        return $this->selection->genres();
    }

    /**
     * The Streaming services the tab offers in the visitor's country, most prominent first, a search by Name
     * included so that choosing one can leave it; none while the source's list is unavailable.
     *
     * @return list<array{id: int, name: string}>
     */
    public function streamingServices(): array
    {
        return $this->selection->streamingServices();
    }

    /**
     * The Streaming services Browse is narrowed to, by id, none in a search by Name.
     *
     * @return list<int>
     */
    public function selectedStreamingServices(): array
    {
        return $this->selection->filters()->streamingServiceIds;
    }

    /** The genre Browse is narrowed to, null for none, and always null in a search by Name. */
    public function genre(): ?int
    {
        return $this->selection->filters()->genreId;
    }

    /** Whether Browse is limited to Titles with a Highly rated TMDB score. */
    public function highlyRated(): bool
    {
        return $this->selection->filters()->highlyRated;
    }

    /** The era Browse is narrowed to, or null for none. */
    public function era(): ?Era
    {
        return $this->selection->filters()->era;
    }

    /** The runtime band Browse is narrowed to, or null for none. */
    public function runtimeBand(): ?RuntimeBand
    {
        return $this->selection->filters()->runtimeBand;
    }

    public function canHideSeen(): bool
    {
        return $this->request->canHideSeen();
    }

    public function showSeen(): bool
    {
        return $this->request->showsSeen();
    }

    /** The original language Browse is narrowed to, or null for none. */
    public function originalLanguage(): ?OriginalLanguage
    {
        return $this->selection->filters()->originalLanguage;
    }

    /** The order Browse is in: the request's `sort` when it is an offered one, else the default of the filters. */
    public function sort(): BrowseSort
    {
        return $this->selection->filters()->sort();
    }

    /** The order the request chose, null when it chose none, and always null in a search by Name. */
    public function explicitSort(): ?BrowseSort
    {
        return $this->selection->filters()->requestedSort;
    }

    /** `fresh`, `stale` or `unavailable`. */
    public function status(): string
    {
        return $this->resultsPage->status();
    }

    /**
     * The viewer's facts for every Movie shown, keyed by catalog id, including Movies with no row yet.
     *
     * @return array<int, array{watchlisted: bool, watched: bool, watchedDate: ?string}>
     */
    public function userMovies(): array
    {
        return $this->cards->userMovies();
    }

    /**
     * Whether the viewer has each TV Show shown on their Watchlist, keyed by catalog id, including TV Shows with no row yet.
     *
     * @return array<int, array{watchlisted: bool}>
     */
    public function userTvShows(): array
    {
        return $this->cards->userTvShows();
    }
}
