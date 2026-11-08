<?php

namespace App\Http\Support;

use App\Actions\RecordEvent;
use App\Models\Movie;
use App\Models\User;
use App\Services\Catalog\CatalogResult;
use App\Services\Catalog\Discover\DiscoverBuilder;
use App\Services\Catalog\Discover\DiscoverMovie;
use App\Services\Catalog\Search\CatalogSearch;
use App\Services\Catalog\Search\ImdbIdLookup;
use App\Services\Catalog\Sources\CatalogSource;
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
 * The Search page's props for one response, asking the Catalog for the search at most once. A query shorter than
 * `search.minimum_query_length` isn't searched. Trending, from the stored Discover payload, shows when there is no
 * search, no result or no answer. Each Movie links to its canonical URL when it has a row, else to the entry route,
 * both with the `search` referrer, and shows one External rating: IMDb when its IMDb id is known, from its row or
 * remembered, and rated, else TMDB. The ids not known are looked up afterwards, by `lookedUpRatings()`.
 * A results page records `movie_searched`; a reload of `userMovies` alone records nothing and asks no source.
 *
 * @phpstan-type Card array{tmdbId: int, title: ?string, year: ?int, posterPath: ?string, ratings: list<array{source: string, name: string, value: float, voteCount: int}>, href: string}
 */
class SearchPage
{
    /** @var CatalogResult<SearchResults>|null */
    private ?CatalogResult $result = null;

    /** @var Collection<int, Movie>|null keyed by catalog id */
    private ?Collection $rows = null;

    /** @var array<int, list<ExternalRating>>|null keyed by catalog id */
    private ?array $ratings = null;

    /** @var list<DiscoverMovie>|null */
    private ?array $trendingMovies = null;

    public function __construct(
        private CatalogSearch $catalogSearch,
        private ImdbIdLookup $imdbIds,
        private DiscoverBuilder $discover,
        private Ratings $ratingSources,
        private RecordEvent $recordEvent,
        private ?User $user,
        public readonly string $query,
        public readonly int $page,
        private bool $readsCacheOnly = false,
    ) {}

    /**
     * The page for the request's `q`, trimmed of Unicode whitespace and cut to `search.maximum_query_length`
     * characters, and its `page`, kept from 1 to the source's last page. A reload of `userMovies` alone, as after
     * a Watchlist action, reads the search from the cache only.
     */
    public static function fromRequest(Request $request, CatalogSearch $catalogSearch, ImdbIdLookup $imdbIds, DiscoverBuilder $discover, Ratings $ratingSources, RecordEvent $recordEvent): self
    {
        return new self(
            $catalogSearch,
            $imdbIds,
            $discover,
            $ratingSources,
            $recordEvent,
            $request->user(),
            self::queryFrom($request),
            self::pageFrom($request),
            self::reloadsUserMoviesOnly($request),
        );
    }

    /**
     * The page asked for by the Search page's lookups of the IMDb ratings: the request's query and page, read as
     * `fromRequest()` reads them, from the cache alone. It searches nothing and records nothing.
     */
    public static function cachedFromRequest(Request $request, CatalogSearch $catalogSearch, ImdbIdLookup $imdbIds, DiscoverBuilder $discover, Ratings $ratingSources, RecordEvent $recordEvent): self
    {
        return new self($catalogSearch, $imdbIds, $discover, $ratingSources, $recordEvent, $request->user(), self::queryFrom($request), self::pageFrom($request), readsCacheOnly: true);
    }

    /** The request's `q`, trimmed of Unicode whitespace and cut to `search.maximum_query_length` characters. */
    private static function queryFrom(Request $request): string
    {
        $query = $request->query('q');

        return is_string($query) ? Str::trim(mb_substr(Str::trim($query), 0, config('catalog.search.maximum_query_length'))) : '';
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

    public function isSearching(): bool
    {
        return mb_strlen($this->query) >= config('catalog.search.minimum_query_length');
    }

    /**
     * The page's results. A signed-in viewer's first page records `movie_searched` with the query's length and the
     * result count, never its text.
     *
     * @return array{data: list<array{tmdbId: int, title: ?string, year: ?int, posterPath: ?string, ratings: list<array{source: string, name: string, value: float, voteCount: int}>, href: string, page: int}>}
     */
    public function results(): array
    {
        $results = $this->search()?->value;

        if ($results !== null && $this->page === 1) {
            $this->recordEvent->handle($this->user, 'movie_searched', ['query_length' => mb_strlen($this->query), 'result_count' => $results->totalResults]);
        }

        return ['data' => array_map(fn (MovieListing $listing): array => [...$this->card($listing->tmdbId, $listing->title, $listing->releaseDate, $listing->posterPath), 'page' => $this->page], $results->listings ?? [])];
    }

    /**
     * The ratings, by catalog id, of the Movies on this page that have an IMDb rating once their IMDb id is known,
     * from a lookup if need be, which the page's cards couldn't show without one. A Movie whose row has its IMDb id
     * isn't listed, since its card already shows it. Only the cached page's own ids are looked up, never ids a
     * visitor supplies, and nothing is looked up while IMDb is switched off.
     *
     * @return array<int, list<array{source: string, name: string, value: float, voteCount: int}>>
     */
    public function lookedUpRatings(): array
    {
        $listings = $this->search()?->value->listings ?? [];

        if (! RatingSourceName::Imdb->isEnabled() || $listings === []) {
            return [];
        }

        $tmdbIdsWithoutImdbId = array_values(array_filter(
            array_map(fn (MovieListing $listing): int => $listing->tmdbId, $listings),
            fn (int $tmdbId): bool => $this->rows()->get($tmdbId)?->imdb_id === null,
        ));
        $imdbIds = $this->imdbIds->lookUpImdbIds($tmdbIdsWithoutImdbId);
        $found = [];

        foreach (RatingsByCatalogId::for($this->ratingSources, $imdbIds) as $tmdbId => $ratings) {
            if (array_filter($ratings, fn (ExternalRating $rating): bool => $rating->source === RatingSourceName::Imdb) !== []) {
                $found[$tmdbId] = array_map(fn (ExternalRating $rating): array => $rating->toArray(), $ratings);
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
        $result = $this->search();

        if ($result !== null && $result->isUnavailable() && $this->page > 1) {
            return new ScrollMetadata('page', $this->page, $this->page, $this->page);
        }

        $lastPage = min($result?->value->totalPages ?? 0, CatalogSource::LAST_SOURCE_PAGE);

        return new ScrollMetadata(
            'page',
            $this->isSearching() && $this->page > 1 ? $this->page - 1 : null,
            $this->page < $lastPage ? $this->page + 1 : null,
            $this->page,
        );
    }

    public function totalResults(): ?int
    {
        return $this->search()?->value?->totalResults;
    }

    /** `fresh`, `stale` or `unavailable`; null with no search. */
    public function status(): ?string
    {
        return $this->search()?->status->value;
    }

    /** @return list<Card> */
    public function trending(): array
    {
        return array_map(fn (DiscoverMovie $movie): array => $this->card($movie->tmdbId, $movie->title, $movie->releaseDate, $movie->posterPath), $this->trendingMovies());
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
     * The search, asked for once; from the cache alone when only `userMovies` is reloaded, and unavailable when
     * the cache has nothing.
     *
     * @return CatalogResult<SearchResults>|null
     */
    private function search(): ?CatalogResult
    {
        if (! $this->isSearching()) {
            return null;
        }

        if ($this->readsCacheOnly) {
            $cached = $this->catalogSearch->cachedSearch($this->query, $this->page);

            return $this->result ??= $cached === null ? CatalogResult::unavailable() : CatalogResult::stale($cached);
        }

        return $this->result ??= $this->catalogSearch->search($this->query, $this->page);
    }

    /** @return list<DiscoverMovie> */
    private function trendingMovies(): array
    {
        $result = $this->search();

        return $this->trendingMovies ??= $result === null || $result->isUnavailable() || $result->value?->totalResults === 0 ? $this->discover->storedTrending() : [];
    }

    /** @return list<int> */
    private function catalogIds(): array
    {
        return array_values(array_unique([
            ...array_map(fn (MovieListing $listing): int => $listing->tmdbId, $this->search()?->value->listings ?? []),
            ...array_map(fn (DiscoverMovie $movie): int => $movie->tmdbId, $this->trendingMovies()),
        ]));
    }

    /** @return Card */
    private function card(int $tmdbId, ?string $title, ?string $releaseDate, ?string $posterPath): array
    {
        $row = $this->rows()->get($tmdbId);

        return [
            'tmdbId' => $tmdbId,
            'title' => $title,
            'year' => $releaseDate === null ? null : (int) substr($releaseDate, 0, 4),
            'posterPath' => $posterPath,
            'ratings' => array_map(fn (ExternalRating $rating): array => $rating->toArray(), $this->ratings()[$tmdbId] ?? []),
            'href' => $row?->canonicalUrl('search') ?? route('movies.entry', ['tmdbId' => $tmdbId, 'referrer' => 'search']),
        ];
    }

    /** @return Collection<int, Movie> keyed by catalog id */
    private function rows(): Collection
    {
        return $this->rows ??= Movie::query()->whereIn('tmdb_id', $this->catalogIds())->get()->keyBy('tmdb_id');
    }

    /**
     * The IMDb ids by catalog id, with those already remembered filling the gaps.
     *
     * @param  array<int, ?string>  $imdbIds
     * @return array<int, ?string>
     */
    private function withRememberedImdbIds(array $imdbIds): array
    {
        $missing = array_keys(array_filter($imdbIds, fn (?string $imdbId): bool => $imdbId === null));

        return array_replace($imdbIds, $this->imdbIds->cachedImdbIds($missing));
    }

    /** @return array<int, list<ExternalRating>> keyed by catalog id */
    private function ratings(): array
    {
        $trendingImdbIds = collect($this->trendingMovies())->mapWithKeys(fn (DiscoverMovie $movie): array => [$movie->tmdbId => $movie->imdbId]);

        return $this->ratings ??= RatingsByCatalogId::for($this->ratingSources, $this->withRememberedImdbIds(collect($this->catalogIds())->mapWithKeys(fn (int $tmdbId): array => [
            $tmdbId => $this->rows()->get($tmdbId)->imdb_id ?? $trendingImdbIds->get($tmdbId),
        ])->all()));
    }
}
