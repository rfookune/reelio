<?php

namespace App\Http\Support\Search;

use App\Enums\Surface;
use App\Enums\TitleKind;
use App\Http\Support\Cards\RatingsByCatalogId;
use App\Http\Support\Cards\UserMovies;
use App\Models\UserTitle;
use App\Services\Catalog\Search\ImdbIdLookup;
use App\Services\Catalog\Sources\Listings\MovieListing;
use App\Services\Ratings\ExternalRating;
use App\Services\Ratings\Ratings;

/**
 * The cards of a page of results, and the viewer's facts about the Titles on them. Each Movie links to its canonical
 * URL when it has a row, else to the entry route, both with the `search` referrer, and shows one External rating: IMDb
 * when its IMDb id is known, from its row or remembered, and rated, else TMDB; the ids not known are looked up
 * afterwards, by `RatingsLookup`. Results hold TV Shows beside Movies, each by its kind and catalog id (`key`: the
 * catalog id, or `tv:` and it for a TV Show), linking through its own entry route. A TV Show has no Movie Watchlist
 * toggle, so `userMovies` covers Movies only and `userTvShows` carries their Watchlist fact alone.
 *
 * @phpstan-type Card array{tmdbId: int, title: ?string, year: ?int, posterPath: ?string, ratings: list<array{source: string, name: string, value: float, voteCount: int}>, href: string}
 * @phpstan-type SearchCard array{kind: string, key: string, tmdbId: int, title: ?string, year: ?int, posterPath: ?string, ratings: list<array{source: string, name: string, value: float, voteCount: int}>, href: string, seasonCount?: ?int}
 */
class SearchCards
{
    /** @var array<int, list<ExternalRating>>|null keyed by catalog id */
    private ?array $ratings = null;

    /** @var array<int, list<ExternalRating>>|null keyed by catalog id */
    private ?array $tvShowRatings = null;

    public function __construct(
        private SearchRequest $request,
        private ListedTitles $titles,
        private ImdbIdLookup $imdbIds,
        private Ratings $ratingSources,
    ) {}

    /**
     * The cards of the page, each with the page it is on.
     *
     * @return array{data: list<array{kind: string, key: string, tmdbId: int, title: ?string, year: ?int, posterPath: ?string, ratings: list<array{source: string, name: string, value: float, voteCount: int}>, href: string, seasonCount?: ?int, page: int}>}
     */
    public function results(): array
    {
        return ['data' => array_map(fn (MovieListing $listing): array => [...$this->card($listing), 'page' => $this->request->page], $this->titles->listings())];
    }

    /**
     * The viewer's facts for every Movie shown, keyed by catalog id, including Movies with no row yet.
     *
     * @return array<int, array{watchlisted: bool, watched: bool, watchedDate: ?string}>
     */
    public function userMovies(): array
    {
        $none = ['watchlisted' => false, 'watched' => false, 'watchedDate' => null];

        return array_replace(array_fill_keys($this->titles->movieCatalogIds(), $none), UserMovies::for($this->request->user, $this->titles->movieRows()));
    }

    /**
     * Whether the viewer has each TV Show shown on their Watchlist, keyed by catalog id, including TV Shows with no row yet.
     *
     * @return array<int, array{watchlisted: bool}>
     */
    public function userTvShows(): array
    {
        $rows = $this->titles->tvShowRows();
        $watchlisted = $this->request->user === null || $rows->isEmpty() ? collect() : UserTitle::query()
            ->whereBelongsTo($this->request->user)->whereIn('title_id', $rows->pluck('id'))->whereNotNull('watchlisted_at')
            ->pluck('title_id')->flip();

        return collect($this->titles->tvShowCatalogIds())->mapWithKeys(fn (int $tmdbId): array => [
            $tmdbId => ['watchlisted' => $rows->has($tmdbId) && $watchlisted->has($rows->get($tmdbId)->id)],
        ])->all();
    }

    /** @return SearchCard */
    private function card(MovieListing $listing): array
    {
        $kind = $listing->kind;
        $tmdbId = $listing->tmdbId;
        $tvShow = $kind === TitleKind::TvShow ? $this->titles->tvShowRows()->get($tmdbId) : null;
        $ratings = $kind === TitleKind::TvShow ? $this->tvShowRatings() : $this->ratings();
        $referrer = Surface::Search->value;

        return [
            'kind' => $kind->value,
            'key' => ListedTitles::key($kind, $tmdbId),
            'tmdbId' => $tmdbId,
            'title' => $listing->title,
            'year' => $listing->releaseDate === null ? null : (int) substr($listing->releaseDate, 0, 4),
            'posterPath' => $listing->posterPath,
            'ratings' => array_map(fn (ExternalRating $rating): array => $rating->toArray(), $ratings[$tmdbId] ?? []),
            'href' => $kind === TitleKind::TvShow
                ? $tvShow?->canonicalUrl(Surface::Search) ?? route('tv-shows.entry', ['tmdbId' => $tmdbId, 'referrer' => $referrer])
                : $this->titles->movieRows()->get($tmdbId)?->canonicalUrl(Surface::Search) ?? route('movies.entry', ['tmdbId' => $tmdbId, 'referrer' => $referrer]),
            ...($kind === TitleKind::TvShow ? ['seasonCount' => $tvShow?->details?->season_count] : []),
        ];
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
        return $this->ratings ??= RatingsByCatalogId::for($this->ratingSources, $this->withRememberedImdbIds(collect($this->titles->movieCatalogIds())->mapWithKeys(fn (int $tmdbId): array => [
            $tmdbId => $this->titles->movieRows()->get($tmdbId)?->imdb_id,
        ])->all()));
    }

    /** @return array<int, list<ExternalRating>> keyed by catalog id */
    private function tvShowRatings(): array
    {
        return $this->tvShowRatings ??= RatingsByCatalogId::for($this->ratingSources, $this->withRememberedImdbIds(collect($this->titles->tvShowCatalogIds())->mapWithKeys(fn (int $tmdbId): array => [
            $tmdbId => $this->titles->tvShowRows()->get($tmdbId)?->imdb_id,
        ])->all(), TitleKind::TvShow), TitleKind::TvShow);
    }
}
