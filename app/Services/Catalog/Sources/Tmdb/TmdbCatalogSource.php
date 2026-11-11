<?php

namespace App\Services\Catalog\Sources\Tmdb;

use App\Enums\TitleKind;
use App\Services\Catalog\CatalogContext;
use App\Services\Catalog\Enums\CatalogProfile;
use App\Services\Catalog\Enums\SectionKind;
use App\Services\Catalog\Sources\CatalogSource;
use App\Services\Catalog\Sources\Details\MovieDetails;
use App\Services\Catalog\Sources\Details\TvShowDetails;
use App\Services\Catalog\Sources\Listings\BrowseFilters;
use App\Services\Catalog\Sources\Listings\BrowseSort;
use App\Services\Catalog\Sources\Listings\SearchResults;
use App\Services\Catalog\Sources\Listings\SectionPage;
use App\Services\Catalog\Sources\Listings\SectionQuery;
use App\Services\Catalog\Sources\SourceUnavailable;
use Illuminate\Http\Client\Response;

class TmdbCatalogSource implements CatalogSource
{
    public function __construct(private TmdbClient $client, private MovieParser $movies, private TvShowParser $tvShows) {}

    public function tvShow(int $tmdbId, CatalogContext $context): ?TvShowDetails
    {
        $deadline = $context->profile === CatalogProfile::Interactive ? microtime(true) + $context->profile->timeoutSeconds() : null;
        $response = $this->client->tvDetails($tmdbId, $context, $deadline);
        if ($response === null) {
            return null;
        }
        $data = $this->tvShows->details((array) $response->json(), $tmdbId);
        $responses = $this->client->seasons($tmdbId, array_column($data['seasons'], 'season_number'), $context, $deadline);
        $seasons = [];
        foreach ($data['seasons'] as $summary) {
            $seasons[] = $this->tvShows->season((array) $responses[$summary['season_number']]->json(), $summary);
        }

        return $this->tvShows->parse($data, $seasons, $context->language);
    }

    public function movie(int $tmdbId, CatalogContext $context): ?MovieDetails
    {
        $response = $this->client->get("movie/{$tmdbId}", [
            'language' => $context->language,
            'include_adult' => 'false',
            'include_video_language' => explode('-', $context->language)[0].',null',
            'append_to_response' => 'credits,videos,release_dates,watch/providers',
        ], $context);

        if ($response === null) {
            return null;
        }

        return $this->movies->parse((array) $response->json(), $tmdbId, $context->language);
    }

    /**
     * One page of a list. Trending is global, so only Coming soon, which is about US release dates, carries a
     * region. It and the genre collections read TMDB's discover list by popularity; Coming soon takes the same
     * release types, theatrical and digital, that its US date is read from. Adult listings, listings that aren't
     * well formed and repeats within the page are dropped; an unsafe image path is cleared.
     */
    public function section(SectionQuery $query, int $page, CatalogContext $context): SectionPage
    {
        $path = match ($query->kind) {
            SectionKind::Trending => 'trending/movie/week',
            SectionKind::Popular => 'movie/popular',
            SectionKind::TopRated => 'movie/top_rated',
            SectionKind::ComingSoon, SectionKind::Genre => 'discover/movie',
        };
        $filters = match ($query->kind) {
            SectionKind::ComingSoon => [
                'region' => 'US',
                'with_release_type' => implode('|', [MovieParser::RELEASE_TYPE_THEATRICAL_LIMITED, MovieParser::RELEASE_TYPE_THEATRICAL, MovieParser::RELEASE_TYPE_DIGITAL]),
                'release_date.gte' => (string) $query->releasedFrom,
                'release_date.lte' => (string) $query->releasedUntil,
                'sort_by' => 'popularity.desc',
            ],
            SectionKind::Genre => [
                'with_genres' => (int) $query->genreId,
                'vote_count.gte' => (int) $query->minimumVotes,
                'sort_by' => 'popularity.desc',
            ],
            default => [],
        };
        $response = $this->client->get($path, ['language' => $context->language, 'include_adult' => 'false', 'page' => $page, ...$filters], $context)
            ?? throw new SourceUnavailable('The Catalog source has no such list page.');
        $data = $this->movies->listPage((array) $response->json());

        return new SectionPage($this->movies->listings($data['results']), $data['total_pages']);
    }

    /**
     * One page of a title search, in TMDB's order, Movies without a poster included. With TV Shows on it searches
     * every kind and keeps the Movies and TV Shows, told apart by `media_type`; with them off, Movies alone. Adult
     * listings, listings that aren't well formed and repeats within the page are dropped; an unsafe image path is
     * cleared.
     */
    public function search(string $query, int $page, CatalogContext $context): SearchResults
    {
        $includesTvShows = (bool) config('catalog.tv_shows.enabled');
        $response = $this->client->get($includesTvShows ? 'search/multi' : 'search/movie', ['query' => $query, 'page' => $page, 'language' => $context->language, 'include_adult' => 'false'], $context)
            ?? throw new SourceUnavailable('The Catalog source has no such search page.');
        $data = $this->movies->listPage((array) $response->json());
        $totalResults = $this->totalResults($response);

        return new SearchResults($includesTvShows ? $this->movies->mixedListings($data['results']) : $this->movies->listings($data['results']), $data['total_pages'], $totalResults);
    }

    /**
     * One page of Browse from TMDB's Movie or TV discover list in the filters' sort order, with no text query. Adult
     * listings, listings that aren't well formed and repeats within the page are dropped; an unsafe image path is
     * cleared.
     */
    public function browse(TitleKind $kind, int $page, CatalogContext $context, BrowseFilters $filters = new BrowseFilters): SearchResults
    {
        $narrowedBy = $filters->genreId === null ? [] : ['with_genres' => $filters->genreId];
        $response = $this->client->get($kind === TitleKind::TvShow ? 'discover/tv' : 'discover/movie', ['page' => $page, 'language' => $context->language, 'include_adult' => 'false', ...$this->sortParameters($filters->sort()), ...$narrowedBy], $context)
            ?? throw new SourceUnavailable('The Catalog source has no such browse page.');
        $data = $this->movies->listPage((array) $response->json());

        return new SearchResults($kind === TitleKind::TvShow ? $this->movies->tvListings($data['results']) : $this->movies->listings($data['results']), $data['total_pages'], $this->totalResults($response));
    }

    /**
     * The discover parameters of a sort. Top rated ranks by score among Titles with at least
     * `search.top_rated_minimum_votes` votes, so a few perfect votes don't win.
     *
     * @return array<string, string|int>
     */
    private function sortParameters(BrowseSort $sort): array
    {
        return match ($sort) {
            BrowseSort::Popular => ['sort_by' => 'popularity.desc'],
            BrowseSort::TopRated => ['sort_by' => 'vote_average.desc', 'vote_count.gte' => config('catalog.search.top_rated_minimum_votes')],
        };
    }

    /** The response's result count; a missing or invalid one is a failure. */
    private function totalResults(Response $response): int
    {
        $totalResults = $response->json('total_results');

        if (! is_int($totalResults) || $totalResults < 0) {
            throw new SourceUnavailable('The Catalog source returned an invalid result count.');
        }

        return $totalResults;
    }

    /**
     * The Movie's IMDb title id from its external ids, a response far smaller than its details. TMDB's 404 means it
     * doesn't know the Movie, and an id that isn't an IMDb title id (`tt` and digits) counts as none.
     */
    public function imdbId(int $tmdbId, CatalogContext $context): ?string
    {
        $response = $this->client->get("movie/{$tmdbId}/external_ids", [], $context);

        if ($response === null) {
            return null;
        }

        return $this->movies->imdbId((array) $response->json(), $tmdbId);
    }

    /** The TV Show's IMDb id from its external ids, read as `imdbId()` reads a Movie's. */
    public function tvImdbId(int $tmdbId, CatalogContext $context): ?string
    {
        $response = $this->client->get("tv/{$tmdbId}/external_ids", [], $context);

        if ($response === null) {
            return null;
        }

        return $this->movies->imdbId((array) $response->json(), $tmdbId);
    }

    /**
     * TMDB's Movie genre list, by id, with names in the context's language.
     *
     * @return list<array{id: int, name: string}>
     */
    public function genres(CatalogContext $context, TitleKind $kind = TitleKind::Movie): array
    {
        $response = $this->client->get($kind === TitleKind::TvShow ? 'genre/tv/list' : 'genre/movie/list', ['language' => $context->language], $context)
            ?? throw new SourceUnavailable('The Catalog source has no genre list.');

        return $this->movies->genres((array) $response->json());
    }
}
