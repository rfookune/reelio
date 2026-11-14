<?php

namespace App\Http\Support;

use App\Enums\ListFilter;
use App\Enums\ListSort;
use App\Enums\ProgressState;
use App\Enums\SortDirection;
use App\Enums\Surface;
use App\Enums\TitleKind;
use App\Enums\UserList;
use App\Http\Support\Cards\RatingsByCatalogId;
use App\Http\Support\Search\SearchCards;
use App\Models\Movie;
use App\Models\Title;
use App\Models\TvShow;
use App\Models\User;
use App\Models\UserMovie;
use App\Models\UserTitle;
use App\Services\Catalog\Discover\DiscoverBuilder;
use App\Services\Catalog\Discover\DiscoverMovie;
use App\Services\Ratings\ExternalRating;
use App\Services\Ratings\Ratings;
use App\Services\TvShows\ProgressSummaries;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Pagination\Cursor;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\ScrollMetadata;

/**
 * Stored Watchlist or Watched cards and a stable cursor, Movies and TV Shows in one keyset-paged query. The
 * Watchlist holds the Titles with a Watchlist fact. Watched holds the Movies with a Watched date and the TV Shows
 * with at least one Episode watched, dated by the latest, a TV Show having no Watched fact of its own. TV Shows are
 * left out while they are switched off, including Removed Titles. No Title is
 * refreshed while listing it.
 *
 * @phpstan-import-type Card from SearchCards
 * @phpstan-import-type SearchCard from SearchCards
 *
 * @phpstan-type ListCard array{kind: string, key: string, tmdbId: int, title: ?string, year: ?int, posterPath: ?string, ratings: list<array{source: string, name: string, value: float, voteCount: int}>, href: string, userRating: ?int}
 * @phpstan-type TvShowFacts array{watchlisted: bool, watched: bool, watchedDate: ?string, state: string, watchedCount: int, total: int}
 */
class UserListPage
{
    /** @var CursorPaginator<int, Title>|null */
    private ?CursorPaginator $titles = null;

    private ?int $count = null;

    public function __construct(private User $user, private UserList $list, private ListSort $sort, private ListFilter $filter, private DiscoverBuilder $discover, private Ratings $ratings, private ProgressSummaries $progress) {}

    /** The whole list's size, whatever the filter, Removed Titles included. */
    private function count(): int
    {
        return $this->count ??= DB::query()->fromSub($this->entries($this->shownKinds(ListFilter::All)), 'entries')->count();
    }

    /** @return array<string, mixed> */
    public function titles(): array
    {
        $page = $this->page();
        $titles = $this->typed($page->getCollection());
        $ratings = $titles->isEmpty() ? [] : $this->ratings->ratingsFor($titles->values());
        $userRatings = $this->userRatingsFor($titles);

        return array_replace($page->toArray(), ['data' => $titles->map(fn (Title $title): array => $this->card($title, $ratings[$title->id] ?? [], $userRatings[$title->id] ?? null))->values()->all()]);
    }

    /**
     * The signed-in user's own rating of each rated Title, keyed by Title id.
     *
     * @param  Collection<int, Movie|TvShow>  $titles
     * @return array<int, int>
     */
    private function userRatingsFor(Collection $titles): array
    {
        if ($titles->isEmpty()) {
            return [];
        }

        return UserTitle::query()->whereBelongsTo($this->user)->whereIn('title_id', $titles->pluck('id'))->whereNotNull('rating')->pluck('rating', 'title_id')->all();
    }

    public function scrollMetadata(): ScrollMetadata
    {
        return ScrollMetadata::fromPaginator($this->page());
    }

    /** @return list<array{tmdbId: int, title: string, year: ?int, posterPath: ?string, ratings: array{}, href: string, date: string}> */
    public function unavailableMovies(): array
    {
        if (! in_array(TitleKind::Movie, $this->shownKinds($this->filter), true)) {
            return [];
        }

        return array_values($this->movieFacts()->whereHas('movie', fn (Builder $query): Builder => $query->whereNull('title'))
            ->with('movie')->orderBy('id')->get()->map(fn (UserMovie $facts): array => [
                'tmdbId' => $facts->movie->tmdb_id,
                'title' => $facts->movie->isRemoved() ? 'Removed movie' : 'Details temporarily unavailable',
                'year' => null,
                'posterPath' => $facts->movie->poster_path,
                'ratings' => [],
                'href' => $facts->movie->canonicalUrl($this->list->surface()),
                'date' => $facts->getAttribute($this->list->dateColumn())->toJSON(),
            ])->all());
    }

    /** @return list<array{kind?: 'tv', key?: string, tmdbId: int, title: string, year: ?int, posterPath: ?string, ratings: array{}, href: string, date: string}> */
    public function unavailableTitles(): array
    {
        $movies = $this->unavailableMovies();
        if (! in_array(TitleKind::TvShow, $this->shownKinds($this->filter), true)) {
            return $movies;
        }
        $shows = TvShow::query()->joinSub($this->entries([TitleKind::TvShow]), 'entries', 'entries.title_id', '=', 'titles.id')
            ->whereNull('titles.title')->orderBy('titles.id')->get(['titles.*', 'entries.entry_date']);

        foreach ($shows as $show) {
            $movies[] = $this->unavailableTvShowCard($show);
        }

        return $movies;
    }

    /** @return array{kind: 'tv', key: string, tmdbId: int, title: string, year: null, posterPath: null, ratings: array{}, href: string, date: string} */
    private function unavailableTvShowCard(TvShow $show): array
    {
        return [
            'kind' => TitleKind::TvShow->value, 'key' => "tv:{$show->tmdb_id}", 'tmdbId' => $show->tmdb_id,
            'title' => $show->isRemoved() ? 'Removed TV show' : 'Details temporarily unavailable',
            'year' => null, 'posterPath' => null, 'ratings' => [], 'href' => $show->canonicalUrl($this->list->surface()),
            'date' => Carbon::parse($show->getAttribute('entry_date'))->toJSON(),
        ];
    }

    /** A fresh page starting at the restored Movie, so Undo can reveal it even after a sort reset. */
    public function restoredMovieUrl(int $tmdbId): string
    {
        $entry = $this->orderedTitles([TitleKind::Movie])->where('titles.tmdb_id', $tmdbId)->first();
        $parameters = $this->parameters();

        if ($entry !== null) {
            $parameters['cursor'] = (new Cursor([
                'sort_key' => $entry->getAttribute('sort_key'),
                'titles.id' => $entry->id + ($this->sort->direction() === SortDirection::Desc ? 1 : -1),
            ]))->encode();
        }

        return route($this->list->value, $parameters);
    }

    /** @return list<Card> */
    public function trending(): array
    {
        $movies = $this->list === UserList::Watchlist && $this->count() === 0 ? $this->discover->storedTrending() : [];
        if ($movies === []) {
            return [];
        }
        $rows = Movie::query()->whereIn('tmdb_id', array_column($movies, 'tmdbId'))->get()->keyBy('tmdb_id');
        $ratings = RatingsByCatalogId::for($this->ratings, collect($movies)->mapWithKeys(fn (DiscoverMovie $movie): array => [$movie->tmdbId => $rows->get($movie->tmdbId)->imdb_id ?? $movie->imdbId])->all());

        return array_map(fn (DiscoverMovie $movie): array => [
            'tmdbId' => $movie->tmdbId,
            'title' => $movie->title,
            'year' => $movie->releaseDate === null ? null : (int) substr($movie->releaseDate, 0, 4),
            'posterPath' => $movie->posterPath,
            'ratings' => array_map(fn (ExternalRating $rating): array => $rating->toArray(), $ratings[$movie->tmdbId] ?? []),
            'href' => $rows->get($movie->tmdbId)?->canonicalUrl(Surface::Watchlist) ?? route('movies.entry', ['tmdbId' => $movie->tmdbId, 'referrer' => Surface::Watchlist->value]),
        ], $movies);
    }

    /**
     * Replace the user's compact facts map on every reload, including after actions. Deleted facts then disappear
     * even for cards on earlier pages, while the cards themselves stay in the merged pages for rollback and Undo.
     *
     * @return array<int, array{watchlisted: bool, watched: bool, watchedDate: ?string}>
     */
    public function userMovies(): array
    {
        return UserMovie::query()->whereBelongsTo($this->user)->with('movie:id,tmdb_id')->get()
            ->mapWithKeys(fn (UserMovie $facts): array => [$facts->movie->tmdb_id => [
                'watchlisted' => $facts->watchlisted_at !== null,
                'watched' => $facts->watched_at !== null,
                'watchedDate' => $facts->watched_at?->toJSON(),
            ]])->all();
    }

    /**
     * The user's facts about every TV Show they hold one about, keyed by catalog id, replaced on every
     * reload like `userMovies`: on the Watchlist, with its derived state and counts, and watched once an Episode is.
     * The lists' counts and which cards show come from these.
     *
     * @return array<int, TvShowFacts>
     */
    public function userTvShows(): array
    {
        if (! config('catalog.tv_shows.enabled')) {
            return [];
        }

        $watchlisted = DB::table('user_titles')->where('user_id', $this->user->id)->whereNotNull('watchlisted_at')->pluck('watchlisted_at', 'title_id');
        $marked = DB::table('user_episodes')->join('episodes', 'episodes.id', '=', 'user_episodes.episode_id')->where('user_episodes.user_id', $this->user->id)->distinct()->pluck('episodes.title_id');
        $ids = array_values(array_unique([...array_map(intval(...), $watchlisted->keys()->all()), ...array_map(intval(...), $marked->all())]));
        $summaries = $this->progress->for($this->user, $ids);

        return TvShow::query()->whereIn('id', $ids)->get(['id', 'tmdb_id'])
            ->mapWithKeys(function (TvShow $tvShow) use ($watchlisted, $summaries): array {
                $summary = $summaries[$tvShow->id] ?? null;

                return [$tvShow->tmdb_id => [
                    'watchlisted' => $watchlisted->has($tvShow->id),
                    'watched' => ($summary['marked'] ?? 0) > 0,
                    'watchedDate' => $summary['latestWatchedAt'] ?? null,
                    'state' => ($summary['state'] ?? ProgressState::NotStarted)->value,
                    'watchedCount' => $summary['watched'] ?? 0,
                    'total' => $summary['total'] ?? 0,
                ]];
            })->all();
    }

    /** @return Builder<UserMovie> */
    private function movieFacts(): Builder
    {
        return UserMovie::query()->whereBelongsTo($this->user)->whereNotNull($this->list->dateColumn());
    }

    /** @return CursorPaginator<int, Title> */
    private function page(): CursorPaginator
    {
        if ($this->titles !== null) {
            return $this->titles;
        }

        return $this->titles = $this->orderedTitles($this->shownKinds($this->filter))
            ->cursorPaginate(48)->appends($this->parameters());
    }

    /**
     * The query string a page of this list keeps: its sort and filter, each left out at its default.
     *
     * @return array<string, string>
     */
    private function parameters(): array
    {
        return [
            ...($this->sort === $this->list->defaultSort() ? [] : ['sort' => $this->sort->value]),
            ...($this->filter === ListFilter::All ? [] : ['filter' => $this->filter->value]),
        ];
    }

    /**
     * The kinds a filter shows: its own, or both for `All`; TV Shows only while they are switched on.
     *
     * @return list<TitleKind>
     */
    private function shownKinds(ListFilter $filter): array
    {
        $kinds = $filter->kind() === null ? [TitleKind::Movie, TitleKind::TvShow] : [$filter->kind()];

        return array_values(array_filter($kinds, fn (TitleKind $kind): bool => $kind === TitleKind::Movie || config('catalog.tv_shows.enabled')));
    }

    /**
     * One row for each Title of the kinds the user has a fact about on this list, with the date of it: the
     * Watchlist's Date added or Watched date, or for a TV Show on Watched its latest Episode's Watched date. Removed Titles are included, as in `userTvShows()`, so the sizes agree.
     *
     * @param  list<TitleKind>  $kinds
     */
    private function entries(array $kinds): QueryBuilder
    {
        $column = $this->list->dateColumn();
        $facts = fn (TitleKind $kind): QueryBuilder => DB::table('user_titles')
            ->join('titles', 'titles.id', '=', 'user_titles.title_id')
            ->where('user_titles.user_id', $this->user->id)->where('titles.kind', $kind->value)->whereNotNull('user_titles.'.$column)
            ->select('user_titles.title_id')->selectRaw('user_titles.'.$column.' as entry_date');
        $episodes = fn (): QueryBuilder => DB::table('user_episodes')
            ->join('episodes', 'episodes.id', '=', 'user_episodes.episode_id')
            ->join('titles', 'titles.id', '=', 'episodes.title_id')
            ->where('user_episodes.user_id', $this->user->id)
            ->groupBy('episodes.title_id')->select('episodes.title_id')->selectRaw('max(user_episodes.watched_at) as entry_date');

        $parts = array_map(fn (TitleKind $kind): QueryBuilder => $kind === TitleKind::TvShow && $this->list === UserList::Watched ? $episodes() : $facts($kind), $kinds);
        $entries = array_shift($parts) ?? DB::table('user_titles')->whereRaw('false')->select('title_id')->selectRaw('user_titles.'.$column.' as entry_date');

        foreach ($parts as $part) {
            $entries->unionAll($part);
        }

        return $entries;
    }

    /**
     * The Titles with details in the entries of the kinds, in the sort's order, a Title's id breaking ties.
     *
     * @param  list<TitleKind>  $kinds
     * @return Builder<Title>
     */
    private function orderedTitles(array $kinds): Builder
    {
        $expression = match ($this->sort) {
            ListSort::Title => '(titles.title COLLATE movie_title)',
            ListSort::NewestRelease => "COALESCE(movie_details.release_date, tv_show_details.first_air_date, '-infinity'::date)",
            ListSort::OldestRelease => "COALESCE(movie_details.release_date, tv_show_details.first_air_date, 'infinity'::date)",
            default => 'entries.entry_date',
        };

        return Title::query()->joinSub($this->entries($kinds), 'entries', 'entries.title_id', '=', 'titles.id')
            ->leftJoin('movie_details', 'movie_details.title_id', '=', 'titles.id')
            ->leftJoin('tv_show_details', 'tv_show_details.title_id', '=', 'titles.id')
            ->whereNotNull('titles.title')->select('titles.*')->selectRaw($expression.' as sort_key')
            ->orderBy('sort_key', $this->sort->direction()->value)->orderBy('titles.id', $this->sort->direction()->value);
    }

    /**
     * The page's Titles as their own kind, in the page's order.
     *
     * @param  Collection<int, Title>  $titles
     * @return Collection<int, Movie|TvShow>
     */
    private function typed(Collection $titles): Collection
    {
        $movies = Movie::query()->whereIn('id', $titles->where('kind', TitleKind::Movie)->pluck('id'))->get()->keyBy('id');
        $tvShows = TvShow::query()->with('details')->whereIn('id', $titles->where('kind', TitleKind::TvShow)->pluck('id'))->get()->keyBy('id');

        return $titles->map(fn (Title $title): Movie|TvShow => $title->kind === TitleKind::TvShow ? $tvShows->get($title->id) : $movies->get($title->id));
    }

    /**
     * @param  list<ExternalRating>  $ratings
     * @return ListCard
     */
    private function card(Movie|TvShow $title, array $ratings, ?int $userRating): array
    {
        $isTvShow = $title instanceof TvShow;

        return [
            'kind' => $title->kind->value,
            'key' => $isTvShow ? "tv:{$title->tmdb_id}" : (string) $title->tmdb_id,
            'tmdbId' => $title->tmdb_id,
            'title' => $title->title,
            'year' => ($isTvShow ? $title->details->first_air_date : $title->release_date)?->year,
            'posterPath' => $title->poster_path,
            'ratings' => array_map(fn (ExternalRating $rating): array => $rating->toArray(), $ratings),
            'href' => $title->canonicalUrl($this->list->surface()),
            'userRating' => $userRating,
        ];
    }
}
