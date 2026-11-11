<?php

namespace App\Http\Support;

use App\Actions\RecordEvent;
use App\Enums\AnalyticsEvent;
use App\Enums\Surface;
use App\Enums\TitleKind;
use App\Models\Movie;
use App\Models\TvShow;
use App\Models\User;
use App\Models\UserTitle;
use App\Services\Catalog\CatalogResult;
use App\Services\Catalog\Search\BrowseGenres;
use App\Services\Catalog\Search\CatalogSearch;
use App\Services\Catalog\Search\ImdbIdLookup;
use App\Services\Catalog\Sources\CatalogSource;
use App\Services\Catalog\Sources\Listings\BrowseFilters;
use App\Services\Catalog\Sources\Listings\BrowseSort;
use App\Services\Catalog\Sources\Listings\MovieListing;
use App\Services\Catalog\Sources\Listings\SearchResults;
use App\Services\Ratings\Enums\RatingSourceName;
use App\Services\Ratings\ExternalRating;
use App\Services\Ratings\Ratings;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Inertia\ScrollMetadata;

/**
 * The Search page's props for one response, asking the Catalog for the search, or for Browse when there is none, at
 * most once. A query shorter than `search.minimum_query_length` isn't searched, and the page lists Browse's Popular
 * Movies, or TV Shows when the request's `tab` is `tv` and they are switched on, a page at a time, as it lists a
 * search's results; the tab is ignored in a search by Name. Browse can be narrowed by a genre of the tab's list, the
 * request's `genre`: an id the tab doesn't offer is carried over to the tab's genre of the same name, if it has one,
 * and any other value is ignored, so only an offered id reaches the source. Browse is sorted by the request's `sort` when it
 * is one of the offered values, else by the default of its filters. A search by Name takes no filters or sort. Each Movie links to its canonical URL when it has a row, else to the entry route,
 * both with the `search` referrer, and shows one External rating: IMDb when its IMDb id is known, from its row or
 * remembered, and rated, else TMDB. The ids not known are looked up afterwards, by `lookedUpRatings()`.
 * Results hold TV Shows beside Movies, each by its kind and catalog id (`key`: the catalog id, or `tv:` and it for a
 * TV Show), linking through its own entry route; with TV Shows off they are dropped. A TV Show has no Movie
 * Watchlist toggle, so `userMovies` covers Movies only and `userTvShows` carries their Watchlist fact alone.
 * A search's results page records `title_searched`; a reload of `userMovies` alone records nothing and asks no source.
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

    private ?BrowseFilters $filters = null;

    /** @var list<array{id: int, name: string}>|null */
    private ?array $tabGenres = null;

    public function __construct(
        private CatalogSearch $catalogSearch,
        private BrowseGenres $browseGenres,
        private ImdbIdLookup $imdbIds,
        private Ratings $ratingSources,
        private RecordEvent $recordEvent,
        private ?User $user,
        public readonly string $query,
        public readonly int $page,
        public readonly TitleKind $browseKind = TitleKind::Movie,
        private ?int $requestedGenre = null,
        private ?BrowseSort $requestedSort = null,
        private bool $readsCacheOnly = false,
    ) {}

    /**
     * The page for the request's `q`, trimmed of Unicode whitespace and cut to `search.maximum_query_length`
     * characters, and its `page`, kept from 1 to the source's last page. A reload of `userMovies` alone, as after
     * a Watchlist action, reads the search from the cache only.
     */
    public static function fromRequest(Request $request, CatalogSearch $catalogSearch, BrowseGenres $browseGenres, ImdbIdLookup $imdbIds, Ratings $ratingSources, RecordEvent $recordEvent): self
    {
        return new self(
            $catalogSearch,
            $browseGenres,
            $imdbIds,
            $ratingSources,
            $recordEvent,
            $request->user(),
            self::queryFrom($request),
            self::pageFrom($request),
            self::tabFrom($request),
            self::genreFrom($request),
            BrowseSort::fromRequestValue($request->query('sort')),
            self::reloadsUserMoviesOnly($request),
        );
    }

    /**
     * The page asked for by the Search page's lookups of the IMDb ratings: the request's query and page, read as
     * `fromRequest()` reads them, from the cache alone. It searches nothing and records nothing.
     */
    public static function cachedFromRequest(Request $request, CatalogSearch $catalogSearch, BrowseGenres $browseGenres, ImdbIdLookup $imdbIds, Ratings $ratingSources, RecordEvent $recordEvent): self
    {
        return new self($catalogSearch, $browseGenres, $imdbIds, $ratingSources, $recordEvent, $request->user(), self::queryFrom($request), self::pageFrom($request), self::tabFrom($request), self::genreFrom($request), BrowseSort::fromRequestValue($request->query('sort')), readsCacheOnly: true);
    }

    /** The request's `q`, trimmed of Unicode whitespace and cut to `search.maximum_query_length` characters. */
    private static function queryFrom(Request $request): string
    {
        $query = $request->query('q');

        return is_string($query) ? Str::trim(mb_substr(Str::trim($query), 0, config('catalog.search.maximum_query_length'))) : '';
    }

    /** The Browse tab the request's `tab` names, Movies unless it is `tv` and TV Shows are switched on. */
    private static function tabFrom(Request $request): TitleKind
    {
        return $request->query('tab') === TitleKind::TvShow->value && config('catalog.tv_shows.enabled') ? TitleKind::TvShow : TitleKind::Movie;
    }

    /** The request's `genre` when it is a plain id of nine digits at most, else null; whether it is offered is checked later. */
    private static function genreFrom(Request $request): ?int
    {
        $genre = $request->query('genre');

        return is_string($genre) && ctype_digit($genre) && strlen($genre) <= 9 ? (int) $genre : null;
    }

    /** The request's `page`, kept from 1 to the source's last page. */
    private static function pageFrom(Request $request): int
    {
        $page = $request->query('page');

        return max(1, min(CatalogSource::LAST_SOURCE_PAGE, is_string($page) && ctype_digit($page) ? (int) $page : 1));
    }

    /** Whether the request is Search's partial reload of `userMovies` alone, which searches nothing new. */
    public static function reloadsUserMoviesOnly(Request $request): bool
    {
        return $request->header('X-Inertia-Partial-Component') === 'Search' && $request->header('X-Inertia-Partial-Data') === 'userMovies';
    }

    /** Whether the query is long enough to search by Name; if not, the page is Browse. */
    public function isSearching(): bool
    {
        return mb_strlen($this->query) >= config('catalog.search.minimum_query_length');
    }

    /**
     * The page's results, a search's or Browse's. A signed-in viewer's first page of a search records `title_searched` with the
     * query's length and the result count, never its text.
     *
     * @return array{data: list<array{kind: string, key: string, tmdbId: int, title: ?string, year: ?int, posterPath: ?string, ratings: list<array{source: string, name: string, value: float, voteCount: int}>, href: string, seasonCount?: ?int, page: int}>}
     */
    public function results(): array
    {
        $results = $this->pageResult()->value;

        if ($this->isSearching() && $results !== null && $this->page === 1) {
            $this->recordEvent->handle($this->user, AnalyticsEvent::TitleSearched, ['query_length' => mb_strlen($this->query), 'result_count' => count($this->listings())]);
        }

        return ['data' => array_map(fn (MovieListing $listing): array => [...$this->card($listing->kind, $listing->tmdbId, $listing->title, $listing->releaseDate, $listing->posterPath), 'page' => $this->page], $this->listings())];
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
     * answer points at itself, so "Show more" asks for it again.
     */
    public function scrollMetadata(): ScrollMetadata
    {
        $result = $this->pageResult();

        if ($result->isUnavailable() && $this->page > 1) {
            return new ScrollMetadata('page', $this->page, $this->page, $this->page);
        }

        $lastPage = min($result->value->totalPages ?? 0, CatalogSource::LAST_SOURCE_PAGE);

        return new ScrollMetadata(
            'page',
            $this->page > 1 ? $this->page - 1 : null,
            $this->page < $lastPage ? $this->page + 1 : null,
            $this->page,
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
        return $this->tabGenres ??= $this->genresOf($this->browseKind);
    }

    /** The genre Browse is narrowed to, null for none, and always null in a search by Name. */
    public function genre(): ?int
    {
        return $this->filters()->genreId;
    }

    /** The order Browse is in: the request's `sort` when it is an offered one, else the default of the filters. */
    public function sort(): BrowseSort
    {
        return $this->filters()->sort();
    }

    /** The order the request chose, null when it chose none, and always null in a search by Name. */
    public function explicitSort(): ?BrowseSort
    {
        return $this->filters()->requestedSort;
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

        return array_replace(array_fill_keys($this->catalogIds(), $none), UserMovies::for($this->user, $this->rows()));
    }

    /**
     * Whether the viewer has each TV Show shown on their Watchlist, keyed by catalog id, including TV Shows with no row yet.
     *
     * @return array<int, array{watchlisted: bool}>
     */
    public function userTvShows(): array
    {
        $rows = $this->tvShowRows();
        $watchlisted = $this->user === null || $rows->isEmpty() ? collect() : UserTitle::query()
            ->whereBelongsTo($this->user)->whereIn('title_id', $rows->pluck('id'))->whereNotNull('watchlisted_at')
            ->pluck('title_id')->flip();

        return collect($this->tvShowCatalogIds())->mapWithKeys(fn (int $tmdbId): array => [
            $tmdbId => ['watchlisted' => $rows->has($tmdbId) && $watchlisted->has($rows->get($tmdbId)->id)],
        ])->all();
    }

    /**
     * The filters of this request, none in a search by Name, each checked against what the source offers.
     */
    private function filters(): BrowseFilters
    {
        return $this->filters ??= $this->isSearching() ? new BrowseFilters : new BrowseFilters($this->requestedGenreOnTab(), $this->requestedSort);
    }

    /**
     * The request's `genre` as the tab's own genre id: the id when the tab offers it, else the id of the tab's genre
     * with the same name as that id has in the other kind's list, else null. A value that isn't a plain id, and
     * any while the tab's list is unavailable, is ignored.
     */
    private function requestedGenreOnTab(): ?int
    {
        $id = $this->requestedGenre;
        $genres = $this->genres();

        if ($id === null || $genres === []) {
            return null;
        }

        if (array_find($genres, fn (array $genre): bool => $genre['id'] === $id) !== null) {
            return $id;
        }

        if (! config('catalog.tv_shows.enabled')) {
            return null;
        }

        $otherGenres = $this->genresOf($this->browseKind === TitleKind::Movie ? TitleKind::TvShow : TitleKind::Movie);
        $name = array_find($otherGenres, fn (array $genre): bool => $genre['id'] === $id)['name'] ?? null;

        return array_find($genres, fn (array $genre): bool => $genre['name'] === $name)['id'] ?? null;
    }

    /**
     * A kind's genres, from the cache alone when only `userMovies` is reloaded.
     *
     * @return list<array{id: int, name: string}>
     */
    private function genresOf(TitleKind $kind): array
    {
        return $this->readsCacheOnly ? $this->browseGenres->cachedFor($kind) : $this->browseGenres->for($kind);
    }

    /**
     * The search, or Browse when there is no query, asked for once; from the cache alone when only `userMovies` is
     * reloaded, and unavailable when the cache has nothing.
     *
     * @return CatalogResult<SearchResults>
     */
    private function pageResult(): CatalogResult
    {
        if ($this->readsCacheOnly) {
            $cached = $this->isSearching() ? $this->catalogSearch->cachedSearch($this->query, $this->page) : $this->catalogSearch->cachedBrowse($this->browseKind, $this->page, $this->filters());

            return $this->result ??= $cached === null ? CatalogResult::unavailable() : CatalogResult::stale($cached);
        }

        return $this->result ??= $this->isSearching() ? $this->catalogSearch->search($this->query, $this->page) : $this->catalogSearch->browse($this->browseKind, $this->page, $this->filters());
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
