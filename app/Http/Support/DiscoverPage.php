<?php

namespace App\Http\Support;

use App\Enums\Surface;
use App\Http\Support\Cards\RatingsByCatalogId;
use App\Http\Support\Cards\UserMovies;
use App\Jobs\BuildDiscover;
use App\Models\Movie;
use App\Models\User;
use App\Services\Catalog\Discover\Discover;
use App\Services\Catalog\Discover\DiscoverBuilder;
use App\Services\Catalog\Discover\DiscoverMovie;
use App\Services\Catalog\Discover\DiscoverSection;
use App\Services\Catalog\Enums\SectionKind;
use App\Services\Ratings\ExternalRating;
use App\Services\Ratings\Ratings;
use Illuminate\Support\Collection;

/**
 * The Discover page's props for one response, read from the payload at most once. Each Movie links to its
 * canonical URL when it has a row, else to the entry route, both with the `discover` referrer. Its runtime and IMDb
 * id come from the payload, else from its row, and its External ratings from one batch `Ratings` call, so a Movie
 * with no row still shows the TMDB score recorded when the payload was built.
 */
class DiscoverPage
{
    private ?Discover $discover = null;

    /** @var Collection<int, Movie>|null keyed by catalog id */
    private ?Collection $rows = null;

    /** @var array<int, list<ExternalRating>>|null keyed by catalog id */
    private ?array $ratings = null;

    public function __construct(private DiscoverBuilder $builder, private Ratings $ratingSources, private ?User $user) {}

    /**
     * @return array{tmdbId: int, title: ?string, year: ?int, runtime: ?int, posterPath: ?string, ratings: list<array{source: string, name: string, value: float, voteCount: int}>, href: string, overview: ?string, backdropPath: ?string, genres: list<string>, trailer: array{key: string, name: string, type: string}|null}|null
     */
    public function hero(): ?array
    {
        $hero = $this->discover()->hero;

        if ($hero === null) {
            return null;
        }

        return [
            ...$this->movie($hero),
            'overview' => $hero->overview,
            'backdropPath' => $hero->backdropPath,
            'genres' => $hero->genres,
            'trailer' => $hero->trailer,
        ];
    }

    /**
     * Each section with a key unique on the page, such as `genre-878` for a genre collection. Coming soon's Movies
     * carry their US date; other sections' carry none.
     *
     * @return list<array{key: string, kind: string, title: string, numbered: bool, movies: list<array<string, mixed>>}>
     */
    public function sections(): array
    {
        return array_map(fn (DiscoverSection $section): array => [
            'key' => $section->key(),
            'kind' => $section->kind->value,
            'title' => $section->title(),
            'numbered' => $section->kind->isNumbered(),
            'movies' => array_map(fn (DiscoverMovie $movie): array => [
                ...$this->movie($movie),
                'opensOn' => $section->kind === SectionKind::ComingSoon ? $movie->opensOn : null,
            ], $section->movies),
        ], $this->discover()->sections);
    }

    /**
     * The viewer's facts for every Movie shown, keyed by catalog id, including Movies with no row yet.
     *
     * @return array<int, array{watchlisted: bool, watched: bool, watchedDate: ?string}>
     */
    public function userMovies(): array
    {
        $none = ['watchlisted' => false, 'watched' => false, 'watchedDate' => null];

        return array_replace(array_fill_keys($this->discover()->catalogIds(), $none), UserMovies::for($this->user, $this->rows()));
    }

    /**
     * The payload. One a visitor built has no row details yet, so the full build is queued; it is unique, so
     * visitors arriving meanwhile queue nothing more. Nothing is queued while there's nothing to show: the source
     * is down, and each visitor's own attempt is held back by the circuit breaker until it recovers.
     */
    private function discover(): Discover
    {
        if ($this->discover === null) {
            $this->discover = $this->builder->discover();

            if (! $this->discover->detailed && ! $this->discover->isEmpty()) {
                BuildDiscover::dispatch();
            }
        }

        return $this->discover;
    }

    /**
     * @return array{tmdbId: int, title: ?string, year: ?int, runtime: ?int, posterPath: ?string, ratings: list<array{source: string, name: string, value: float, voteCount: int}>, href: string}
     */
    private function movie(DiscoverMovie $movie): array
    {
        $row = $this->rows()->get($movie->tmdbId);

        return [
            'tmdbId' => $movie->tmdbId,
            'title' => $movie->title,
            'year' => $movie->releaseDate === null ? null : (int) substr($movie->releaseDate, 0, 4),
            'runtime' => $movie->runtime ?? $row?->runtime,
            'posterPath' => $movie->posterPath,
            'ratings' => array_map(fn (ExternalRating $rating): array => $rating->toArray(), $this->ratings()[$movie->tmdbId] ?? []),
            'href' => $row?->canonicalUrl(Surface::Discover) ?? route('movies.entry', ['tmdbId' => $movie->tmdbId, 'referrer' => Surface::Discover->value]),
        ];
    }

    /** @return Collection<int, Movie> keyed by catalog id */
    private function rows(): Collection
    {
        return $this->rows ??= Movie::query()
            ->whereIn('tmdb_id', $this->discover()->catalogIds())
            ->get()
            ->keyBy('tmdb_id');
    }

    /**
     * Each Movie's External ratings, keyed by catalog id.
     *
     * @return array<int, list<ExternalRating>>
     */
    private function ratings(): array
    {
        return $this->ratings ??= RatingsByCatalogId::for($this->ratingSources, collect($this->discover()->movies())->mapWithKeys(fn (DiscoverMovie $movie): array => [
            $movie->tmdbId => $movie->imdbId ?? $this->rows()->get($movie->tmdbId)?->imdb_id,
        ])->all());
    }
}
