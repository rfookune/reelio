<?php

namespace App\Http\Support\Search;

use App\Actions\RecordEvent;
use App\Enums\AnalyticsEvent;
use App\Enums\Surface;
use App\Enums\TitleKind;
use App\Http\Support\Cards\RatingsByCatalogId;
use App\Http\Support\Cards\UserMovies;
use App\Models\Movie;
use App\Models\TvShow;
use App\Models\UserTitle;
use App\Services\Catalog\CatalogResult;
use App\Services\Catalog\Search\BrowseOptions;
use App\Services\Catalog\Search\CatalogSearch;
use App\Services\Catalog\Search\ImdbIdLookup;
use App\Services\Catalog\Sources\CatalogSource;
use App\Services\Catalog\Sources\Listings\BrowseSort;
use App\Services\Catalog\Sources\Listings\Era;
use App\Services\Catalog\Sources\Listings\MovieListing;
use App\Services\Catalog\Sources\Listings\OriginalLanguage;
use App\Services\Catalog\Sources\Listings\RuntimeBand;
use App\Services\Catalog\Sources\Listings\SearchResults;
use App\Services\Ratings\Enums\RatingSourceName;
use App\Services\Ratings\ExternalRating;
use App\Services\Ratings\Ratings;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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
 *
 * @phpstan-type Card array{tmdbId: int, title: ?string, year: ?int, posterPath: ?string, ratings: list<array{source: string, name: string, value: float, voteCount: int}>, href: string}
 * @phpstan-type SearchCard array{kind: string, key: string, tmdbId: int, title: ?string, year: ?int, posterPath: ?string, ratings: list<array{source: string, name: string, value: float, voteCount: int}>, href: string, seasonCount?: ?int}
 */
class SearchPage
{
    /** @var CatalogResult<SearchResults>|null */
    private ?CatalogResult $result = null;

    /** @var Collection<int, Movie>|null keyed by catalog id */
    private ?Collection $rows = null;

    /** @var Collection<int, TvShow>|null keyed by catalog id */
    private ?Collection $tvShowRows = null;

    /** @var array<int, list<ExternalRating>>|null keyed by catalog id */
    private ?array $ratings = null;

    /** @var array<int, list<ExternalRating>>|null keyed by catalog id */
    private ?array $tvShowRatings = null;

    /** @var array{result: CatalogResult<SearchResults>, nextPage: ?int}|null */
    private ?array $unseenPage = null;

    public function __construct(
        private CatalogSearch $catalogSearch,
        private BrowseSelection $selection,
        private UnseenBrowse $unseenBrowse,
        private ImdbIdLookup $imdbIds,
        private Ratings $ratingSources,
        private RecordEvent $recordEvent,
        public readonly SearchRequest $request,
    ) {}

    /** The page for a request, which reads the Catalog live unless it is a reload of `userMovies` alone. */
    public static function fromRequest(Request $request, CatalogSearch $catalogSearch, BrowseOptions $browseOptions, UnseenBrowse $unseenBrowse, ImdbIdLookup $imdbIds, Ratings $ratingSources, RecordEvent $recordEvent): self
    {
        $searchRequest = SearchRequest::from($request);

        return new self($catalogSearch, new BrowseSelection($searchRequest, $browseOptions), $unseenBrowse, $imdbIds, $ratingSources, $recordEvent, $searchRequest);
    }

    /**
     * The page asked for by the Search page's lookups of the IMDb ratings: the request's query and page, read as
     * `fromRequest()` reads them, from the cache alone. It searches nothing and records nothing.
     */
    public static function cachedFromRequest(Request $request, CatalogSearch $catalogSearch, BrowseOptions $browseOptions, UnseenBrowse $unseenBrowse, ImdbIdLookup $imdbIds, Ratings $ratingSources, RecordEvent $recordEvent): self
    {
        $searchRequest = SearchRequest::from($request, CacheMode::CachedPage);

        return new self($catalogSearch, new BrowseSelection($searchRequest, $browseOptions), $unseenBrowse, $imdbIds, $ratingSources, $recordEvent, $searchRequest);
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
        $results = $this->pageResult()->value;

        if ($this->request->isSearching() && $results !== null && $this->request->page === 1) {
            $this->recordEvent->handle($this->request->user, AnalyticsEvent::TitleSearched, ['query_length' => mb_strlen($this->request->query), 'result_count' => count($this->listings())]);
        }

        if (! $this->request->isSearching() && $results !== null && $this->request->page === 1) {
            $this->recordEvent->handle($this->request->user, AnalyticsEvent::TitleBrowsed, [
                'tab' => $this->request->kind->value,
                'filters' => implode(',', [...$this->selection->filters()->usedNames(), ...($this->showSeen() ? ['show_seen'] : [])]),
                'result_count' => count($this->listings()),
            ]);
        }

        return ['data' => array_map(fn (MovieListing $listing): array => [...$this->card($listing->kind, $listing->tmdbId, $listing->title, $listing->releaseDate, $listing->posterPath), 'page' => $this->request->page], $this->listings())];
    }

    /**
     * The ratings of the Movies and TV Shows on this page that have an IMDb rating once their IMDb id is known,
     * from a lookup if need be, which the page's cards couldn't show without one. Keyed by catalog id for a Movie
     * and `tv:` and the catalog id for a TV Show. A title whose row has its IMDb id isn't listed, since its card
     * already shows it. Only the cached page's own ids are looked up, never ids a visitor supplies, and nothing is
     * looked up while IMDb is switched off.
     *
     * @return array<int|string, list<array{source: string, name: string, value: float, voteCount: int}>>
     */
    public function lookedUpRatings(): array
    {
        if (! RatingSourceName::Imdb->isEnabled()) {
            return [];
        }

        $found = [];

        foreach ([TitleKind::Movie, TitleKind::TvShow] as $kind) {
            $tmdbIds = array_map(fn (MovieListing $listing): int => $listing->tmdbId, array_filter($this->listings(), fn (MovieListing $listing): bool => $listing->kind === $kind));
            $tmdbIdsWithoutImdbId = array_values(array_filter($tmdbIds, fn (int $tmdbId): bool => $this->rowsOf($kind)->get($tmdbId)?->imdb_id === null));

            if ($tmdbIdsWithoutImdbId === []) {
                continue;
            }

            $imdbIds = $this->imdbIds->lookUpImdbIds($tmdbIdsWithoutImdbId, $kind);

            foreach (RatingsByCatalogId::for($this->ratingSources, $imdbIds, $kind) as $tmdbId => $ratings) {
                if (array_filter($ratings, fn (ExternalRating $rating): bool => $rating->source === RatingSourceName::Imdb) !== []) {
                    $found[$this->key($kind, $tmdbId)] = array_map(fn (ExternalRating $rating): array => $rating->toArray(), $ratings);
                }
            }
        }

        return $found;
    }

    /**
     * The pages around this one: none past the search's last page or the source's. A later page the source can't
     * answer points at itself, so "Show more" asks for it again. Browse that hides what the viewer has seen is
     * paged by source page: the next page is the one after the last source page read, and there is no previous one.
     */
    public function scrollMetadata(): ScrollMetadata
    {
        $result = $this->pageResult();

        if ($this->request->hidesSeen() && ! $result->isUnavailable()) {
            return new ScrollMetadata('page', null, $this->unseenPage()['nextPage'], $this->request->page);
        }

        if ($result->isUnavailable() && $this->request->page > 1) {
            return new ScrollMetadata('page', $this->request->page, $this->request->page, $this->request->page);
        }

        $lastPage = min($result->value->totalPages ?? 0, CatalogSource::LAST_SOURCE_PAGE);

        return new ScrollMetadata(
            'page',
            $this->request->page > 1 ? $this->request->page - 1 : null,
            $this->request->page < $lastPage ? $this->request->page + 1 : null,
            $this->request->page,
        );
    }

    public function totalResults(): ?int
    {
        return $this->pageResult()->value?->totalResults;
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
        return $this->pageResult()->status->value;
    }

    /**
     * The viewer's facts for every Movie shown, keyed by catalog id, including Movies with no row yet.
     *
     * @return array<int, array{watchlisted: bool, watched: bool, watchedDate: ?string}>
     */
    public function userMovies(): array
    {
        $none = ['watchlisted' => false, 'watched' => false, 'watchedDate' => null];

        return array_replace(array_fill_keys($this->catalogIds(), $none), UserMovies::for($this->request->user, $this->rows()));
    }

    /**
     * Whether the viewer has each TV Show shown on their Watchlist, keyed by catalog id, including TV Shows with no row yet.
     *
     * @return array<int, array{watchlisted: bool}>
     */
    public function userTvShows(): array
    {
        $rows = $this->tvShowRows();
        $watchlisted = $this->request->user === null || $rows->isEmpty() ? collect() : UserTitle::query()
            ->whereBelongsTo($this->request->user)->whereIn('title_id', $rows->pluck('id'))->whereNotNull('watchlisted_at')
            ->pluck('title_id')->flip();

        return collect($this->tvShowCatalogIds())->mapWithKeys(fn (int $tmdbId): array => [
            $tmdbId => ['watchlisted' => $rows->has($tmdbId) && $watchlisted->has($rows->get($tmdbId)->id)],
        ])->all();
    }

    /**
     * The search, or Browse when there is no query, asked for once; from the cache alone when only `userMovies` is
     * reloaded, and unavailable when the cache has nothing.
     *
     * @return CatalogResult<SearchResults>
     */
    private function pageResult(): CatalogResult
    {
        if ($this->request->hidesSeen()) {
            return $this->result ??= $this->unseenPage()['result'];
        }

        if ($this->request->cacheMode->readsCacheOnly()) {
            $cached = $this->request->isSearching() ? $this->catalogSearch->cachedSearch($this->request->query, $this->request->page) : $this->catalogSearch->cachedBrowse($this->request->kind, $this->request->page, $this->selection->filters());

            return $this->result ??= $cached === null ? CatalogResult::unavailable() : CatalogResult::stale($cached);
        }

        return $this->result ??= $this->request->isSearching() ? $this->catalogSearch->search($this->request->query, $this->request->page) : $this->catalogSearch->browse($this->request->kind, $this->request->page, $this->selection->filters());
    }

    /**
     * Browse without the Titles the viewer has seen, read once: the page's source pages from its own, filled
     * from the next ones as `UnseenBrowse` does, from the cache alone when only `userMovies` is reloaded, in which case
     * the Titles seen are kept so that every Title on screen gets its facts.
     *
     * @return array{result: CatalogResult<SearchResults>, nextPage: ?int}
     */
    private function unseenPage(): array
    {
        return $this->unseenPage ??= $this->unseenBrowse->page($this->request->user, $this->request->kind, $this->request->page, $this->selection->filters(), $this->request->cacheMode->readsCacheOnly(), $this->request->cacheMode->keepsSeenTitles());
    }

    /** @return list<int> */
    private function catalogIds(): array
    {
        return array_values(array_unique(array_map(fn (MovieListing $listing): int => $listing->tmdbId, array_filter($this->listings(), fn (MovieListing $listing): bool => $listing->kind === TitleKind::Movie))));
    }

    /** @return SearchCard */
    private function card(TitleKind $kind, int $tmdbId, ?string $title, ?string $releaseDate, ?string $posterPath): array
    {
        $tvShow = $kind === TitleKind::TvShow ? $this->tvShowRows()->get($tmdbId) : null;
        $ratings = $kind === TitleKind::TvShow ? $this->tvShowRatings() : $this->ratings();
        $referrer = Surface::Search->value;

        return [
            'kind' => $kind->value,
            'key' => $this->key($kind, $tmdbId),
            'tmdbId' => $tmdbId,
            'title' => $title,
            'year' => $releaseDate === null ? null : (int) substr($releaseDate, 0, 4),
            'posterPath' => $posterPath,
            'ratings' => array_map(fn (ExternalRating $rating): array => $rating->toArray(), $ratings[$tmdbId] ?? []),
            'href' => $kind === TitleKind::TvShow
                ? $tvShow?->canonicalUrl(Surface::Search) ?? route('tv-shows.entry', ['tmdbId' => $tmdbId, 'referrer' => $referrer])
                : $this->rows()->get($tmdbId)?->canonicalUrl(Surface::Search) ?? route('movies.entry', ['tmdbId' => $tmdbId, 'referrer' => $referrer]),
            ...($kind === TitleKind::TvShow ? ['seasonCount' => $tvShow?->details?->season_count] : []),
        ];
    }

    /** The catalog id for a Movie, and `tv:` and it for a TV Show. */
    private function key(TitleKind $kind, int $tmdbId): string
    {
        return $kind === TitleKind::TvShow ? "tv:{$tmdbId}" : (string) $tmdbId;
    }

    /**
     * The search's listings in the source's order, TV Shows dropped while they are switched off.
     *
     * @return list<MovieListing>
     */
    private function listings(): array
    {
        $listings = $this->pageResult()->value->listings ?? [];

        return config('catalog.tv_shows.enabled') ? $listings : array_values(array_filter($listings, fn (MovieListing $listing): bool => $listing->kind === TitleKind::Movie));
    }

    /** @return Collection<int, Movie>|Collection<int, TvShow> keyed by catalog id */
    private function rowsOf(TitleKind $kind): Collection
    {
        return $kind === TitleKind::TvShow ? $this->tvShowRows() : $this->rows();
    }

    /** @return Collection<int, Movie> keyed by catalog id */
    private function rows(): Collection
    {
        return $this->rows ??= Movie::query()->whereIn('tmdb_id', $this->catalogIds())->get()->keyBy('tmdb_id');
    }

    /** @return Collection<int, TvShow> keyed by catalog id */
    private function tvShowRows(): Collection
    {
        return $this->tvShowRows ??= TvShow::query()->with('details')->whereIn('tmdb_id', $this->tvShowCatalogIds())->get()->keyBy('tmdb_id');
    }

    /** @return list<int> */
    private function tvShowCatalogIds(): array
    {
        return array_values(array_unique(array_map(fn (MovieListing $listing): int => $listing->tmdbId, array_filter($this->listings(), fn (MovieListing $listing): bool => $listing->kind === TitleKind::TvShow))));
    }

    /**
     * The IMDb ids by catalog id, with those already remembered filling the gaps.
     *
     * @param  array<int, ?string>  $imdbIds
     * @return array<int, ?string>
     */
    private function withRememberedImdbIds(array $imdbIds, TitleKind $kind = TitleKind::Movie): array
    {
        $missing = array_keys(array_filter($imdbIds, fn (?string $imdbId): bool => $imdbId === null));

        return array_replace($imdbIds, $this->imdbIds->cachedImdbIds($missing, $kind));
    }

    /** @return array<int, list<ExternalRating>> keyed by catalog id */
    private function ratings(): array
    {
        return $this->ratings ??= RatingsByCatalogId::for($this->ratingSources, $this->withRememberedImdbIds(collect($this->catalogIds())->mapWithKeys(fn (int $tmdbId): array => [
            $tmdbId => $this->rows()->get($tmdbId)?->imdb_id,
        ])->all()));
    }

    /** @return array<int, list<ExternalRating>> keyed by catalog id */
    private function tvShowRatings(): array
    {
        return $this->tvShowRatings ??= RatingsByCatalogId::for($this->ratingSources, $this->withRememberedImdbIds(collect($this->tvShowCatalogIds())->mapWithKeys(fn (int $tmdbId): array => [
            $tmdbId => $this->tvShowRows()->get($tmdbId)?->imdb_id,
        ])->all(), TitleKind::TvShow), TitleKind::TvShow);
    }
}
