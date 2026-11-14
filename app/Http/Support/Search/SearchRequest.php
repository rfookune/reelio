<?php

namespace App\Http\Support\Search;

use App\Enums\TitleKind;
use App\Http\Support\Visitor\VisitorCountry;
use App\Models\User;
use App\Services\Catalog\Sources\CatalogSource;
use App\Services\Catalog\Sources\Listings\BrowseSort;
use App\Services\Catalog\Sources\Listings\Era;
use App\Services\Catalog\Sources\Listings\OriginalLanguage;
use App\Services\Catalog\Sources\Listings\RuntimeBand;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * What one request to the Search page asks for, read from the URL and checked only for its shape: whether a value is
 * one the Catalog offers is for `BrowseSelection` to check, so the `requested` values are what the visitor sent. A
 * value that isn't of the right shape is dropped here, and a request with a query and filters is a search by Name,
 * which takes no filters.
 */
readonly class SearchRequest
{
    /**
     * @param  list<int>  $requestedStreamingServices
     */
    public function __construct(
        public ?User $user,
        public string $country,
        public string $query,
        public int $page,
        public TitleKind $kind,
        public ?int $requestedGenre,
        public ?BrowseSort $requestedSort,
        public array $requestedStreamingServices,
        public bool $requestedHighlyRated,
        public ?Era $requestedEra,
        public ?RuntimeBand $requestedRuntimeBand,
        public ?OriginalLanguage $requestedOriginalLanguage,
        public bool $requestedShowSeen,
        public CacheMode $cacheMode,
        public bool $rendersResults,
    ) {}

    /**
     * The request's `q`, trimmed of Unicode whitespace and cut to `search.maximum_query_length` characters, and its
     * `page`, kept from 1 to the source's last page. A reload of `userMovies` alone, as after a Watchlist action, is
     * read from the cache with the Titles seen kept; the lookup of IMDb ratings says its own mode.
     */
    public static function from(Request $request, ?CacheMode $cacheMode = null): self
    {
        return new self(
            user: $request->user(),
            country: VisitorCountry::resolve($request)->code,
            query: self::queryFrom($request),
            page: self::pageFrom($request),
            kind: self::kindFrom($request),
            requestedGenre: self::genreFrom($request),
            requestedSort: BrowseSort::fromRequestValue($request->query('sort')),
            requestedStreamingServices: self::streamingServicesFrom($request),
            requestedHighlyRated: $request->query('highly_rated') === '1',
            requestedEra: self::enumFrom($request, 'era', Era::class),
            requestedRuntimeBand: self::enumFrom($request, 'runtime', RuntimeBand::class),
            requestedOriginalLanguage: self::enumFrom($request, 'language', OriginalLanguage::class),
            requestedShowSeen: $request->query('show_seen') === '1',
            cacheMode: $cacheMode ?? (self::reloadsUserMoviesOnly($request) ? CacheMode::CachedFacts : CacheMode::Live),
            rendersResults: self::rendersResults($request),
        );
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

    /** Whether the viewer can hide the Titles they have seen: they are signed in. Guests have no such Titles and no toggle. */
    public function canHideSeen(): bool
    {
        return $this->user !== null;
    }

    /** Whether the viewer chose to show the Titles they have seen; never for a guest, nor in a search by Name. */
    public function showsSeen(): bool
    {
        return $this->canHideSeen() && ! $this->isSearching() && $this->requestedShowSeen;
    }

    /** Whether Browse leaves out the Titles the viewer has seen: a signed-in viewer's Browse, unless they show them. */
    public function hidesSeen(): bool
    {
        return $this->canHideSeen() && ! $this->isSearching() && ! $this->requestedShowSeen;
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

    /**
     * The Browse tab the request's `tab` names, Movies unless it is `tv`. A runtime
     * band is for Movies, so it keeps Browse on the Movies tab.
     */
    private static function kindFrom(Request $request): TitleKind
    {
        if (mb_strlen(self::queryFrom($request)) < config('catalog.search.minimum_query_length') && self::enumFrom($request, 'runtime', RuntimeBand::class) !== null) {
            return TitleKind::Movie;
        }

        return $request->query('tab') === TitleKind::TvShow->value ? TitleKind::TvShow : TitleKind::Movie;
    }

    /** The request's `genre` when it is a plain id of nine digits at most, else null; whether it is offered is checked later. */
    private static function genreFrom(Request $request): ?int
    {
        $genre = $request->query('genre');

        return is_string($genre) && ctype_digit($genre) && strlen($genre) <= 9 ? (int) $genre : null;
    }

    /**
     * The ids in the request's `services`, a comma-separated list: the plain ids of nine digits at most, each once,
     * and no more than a few times the number allowed. Whether they are offered is checked later.
     *
     * @return list<int>
     */
    private static function streamingServicesFrom(Request $request): array
    {
        $services = $request->query('services');

        if (! is_string($services)) {
            return [];
        }

        $ids = [];

        foreach (array_slice(explode(',', $services), 0, 50) as $id) {
            if (ctype_digit($id) && strlen($id) <= 9 && (int) $id > 0) {
                $ids[] = (int) $id;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * The case the request's parameter names, when it is a string and one of the enum's values, else none.
     *
     * @template T of \BackedEnum
     *
     * @param  class-string<T>  $enum
     * @return T|null
     */
    private static function enumFrom(Request $request, string $parameter, string $enum): mixed
    {
        $value = $request->query($parameter);

        return is_string($value) ? $enum::tryFrom($value) : null;
    }

    /**
     * Whether the response carries the `results` prop: every visit does, and so does a partial reload that asks for
     * it, but one that leaves it out, or only reloads other props, doesn't read the results at all.
     */
    private static function rendersResults(Request $request): bool
    {
        if ($request->header('X-Inertia-Partial-Component') !== 'Search') {
            return true;
        }

        $only = array_filter(explode(',', (string) $request->header('X-Inertia-Partial-Data')));
        $except = array_filter(explode(',', (string) $request->header('X-Inertia-Partial-Except')));

        return ($only === [] || in_array('results', $only, true)) && ! in_array('results', $except, true);
    }
}
