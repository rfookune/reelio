<?php

namespace App\Http\Support;

use App\Enums\MovieListSort;
use App\Enums\SortDirection;
use App\Enums\Surface;
use App\Enums\UserMovieList;
use App\Models\Movie;
use App\Models\User;
use App\Models\UserMovie;
use App\Services\Catalog\Discover\DiscoverBuilder;
use App\Services\Catalog\Discover\DiscoverMovie;
use App\Services\Ratings\ExternalRating;
use App\Services\Ratings\Ratings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\Cursor;
use Illuminate\Pagination\CursorPaginator;
use Inertia\ScrollMetadata;

/**
 * Stored Watchlist or Watched cards and a stable cursor. No Movie is refreshed while listing it.
 *
 * @phpstan-import-type Card from SearchPage
 */
class UserMovieListPage
{
    /** @var CursorPaginator<int, UserMovie>|null */
    private ?CursorPaginator $movies = null;

    private ?int $count = null;

    public function __construct(private User $user, private UserMovieList $list, private MovieListSort $sort, private DiscoverBuilder $discover, private Ratings $ratings) {}

    private function count(): int
    {
        return $this->count ??= $this->facts()->count();
    }

    /** @return array<string, mixed> */
    public function movies(): array
    {
        $page = $this->page();
        $ratings = $page->isEmpty() ? [] : $this->ratings->ratingsFor($page->getCollection()->map(fn (UserMovie $facts): Movie => $facts->movie));

        return array_replace($page->toArray(), ['data' => $page->getCollection()->map(fn (UserMovie $facts): array => $this->card($facts->movie, $ratings[$facts->title_id] ?? []))->all()]);
    }

    public function scrollMetadata(): ScrollMetadata
    {
        return ScrollMetadata::fromPaginator($this->page());
    }

    /** @return list<array{tmdbId: int, title: string, year: ?int, posterPath: ?string, ratings: array{}, href: string, date: string}> */
    public function unavailableMovies(): array
    {
        return array_values($this->facts()->whereHas('movie', fn (Builder $query): Builder => $query->whereNull('title'))
            ->with('movie')->orderBy('id')->get()->map(fn (UserMovie $facts): array => [
                ...$this->card($facts->movie, []),
                'title' => $facts->movie->isRemoved() ? 'Removed movie' : 'Details temporarily unavailable',
                'date' => $facts->getAttribute($this->list->dateColumn())->toJSON(),
            ])->all());
    }

    /** A fresh page starting at the restored Movie, so Undo can reveal it even after a sort reset. */
    public function restoredMovieUrl(int $tmdbId): string
    {
        $facts = $this->orderedFacts()->where('titles.tmdb_id', $tmdbId)->first();
        $parameters = $this->sort === $this->list->defaultSort() ? [] : ['sort' => $this->sort->value];

        if ($facts !== null) {
            $parameters['cursor'] = (new Cursor([
                'sort_key' => $facts->getAttribute('sort_key'),
                'user_titles.id' => $facts->id + ($this->sort->direction() === SortDirection::Desc ? 1 : -1),
            ]))->encode();
        }

        return route($this->list->value, $parameters);
    }

    /** @return list<Card> */
    public function trending(): array
    {
        $movies = $this->list === UserMovieList::Watchlist && $this->count() === 0 ? $this->discover->storedTrending() : [];
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

    /** @return Builder<UserMovie> */
    private function facts(): Builder
    {
        return UserMovie::query()->whereBelongsTo($this->user)->whereNotNull($this->list->dateColumn());
    }

    /** @return CursorPaginator<int, UserMovie> */
    private function page(): CursorPaginator
    {
        if ($this->movies !== null) {
            return $this->movies;
        }

        return $this->movies = $this->orderedFacts()->with('movie')
            ->cursorPaginate(48)->appends($this->sort === $this->list->defaultSort() ? [] : ['sort' => $this->sort->value]);
    }

    /** @return Builder<UserMovie> */
    private function orderedFacts(): Builder
    {
        $expression = match ($this->sort) {
            MovieListSort::Title => '(titles.title COLLATE movie_title)',
            MovieListSort::NewestRelease => "COALESCE(movie_details.release_date, '-infinity'::date)",
            MovieListSort::OldestRelease => "COALESCE(movie_details.release_date, 'infinity'::date)",
            default => 'user_titles.'.$this->list->dateColumn(),
        };

        return $this->facts()->join('titles', 'titles.id', '=', 'user_titles.title_id')
            ->leftJoin('movie_details', 'movie_details.title_id', '=', 'titles.id')
            ->whereNotNull('titles.title')->select('user_titles.*')->selectRaw($expression.' as sort_key')
            ->orderBy('sort_key', $this->sort->direction()->value)->orderBy('user_titles.id', $this->sort->direction()->value);
    }

    /**
     * @param  list<ExternalRating>  $ratings
     * @return Card
     */
    private function card(Movie $movie, array $ratings): array
    {
        return [
            'tmdbId' => $movie->tmdb_id,
            'title' => $movie->title,
            'year' => $movie->release_date?->year,
            'posterPath' => $movie->poster_path,
            'ratings' => array_map(fn (ExternalRating $rating): array => $rating->toArray(), $ratings),
            'href' => $movie->canonicalUrl($this->list->surface()),
        ];
    }
}
