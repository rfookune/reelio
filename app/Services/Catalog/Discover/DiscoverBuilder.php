<?php

namespace App\Services\Catalog\Discover;

use App\Enums\TitleKind;
use App\Services\Catalog\CacheKey;
use App\Services\Catalog\CatalogContext;
use App\Services\Catalog\Enums\CatalogProfile;
use App\Services\Catalog\Enums\SectionKind;
use App\Services\Catalog\MisconfigurationReporter;
use App\Services\Catalog\Sources\CatalogSource;
use App\Services\Catalog\Sources\Details\MovieDetails;
use App\Services\Catalog\Sources\Listings\MovieListing;
use App\Services\Catalog\Sources\Listings\SectionQuery;
use App\Services\Catalog\Sources\SourceUnavailable;
use App\Services\Ratings\Enums\RatingSourceName;
use App\Services\Ratings\RatingStore;
use App\Services\Ratings\TmdbRatingId;
use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;

/**
 * Builds and stores the Discover payload, the same for every visitor: the hero and each section's Movies, in page
 * order. The Catalog reads it through `stored()`; only `build()` asks the source for it.
 */
class DiscoverBuilder
{
    /** When the build in progress stops asking the source, as a timestamp. */
    private int $deadline = 0;

    public function __construct(
        private CatalogSource $source,
        private CacheKey $keys,
        private RatingStore $ratings,
        private MisconfigurationReporter $misconfiguration,
    ) {}

    /**
     * Rebuilds the payload. Sections are filled in page order (Trending, Popular, Coming soon, Top rated, then the
     * day's genre collections), each Movie only in the earliest section that has it, leaving out Movies without a
     * poster; a later section fills up from its next pages. The hero, the highest-ranked Trending Movie with a
     * backdrop and an overview, stays in Trending, so Trending keeps its true ranks. A section or hero that can't be
     * rebuilt keeps its previous version while it is fresh enough; a genre collection keeps the previous build's for
     * its genre, else the one in its place, unless the day's own collections already show that genre. Every listed Movie's TMDB
     * score is recorded, with or without a Movie row.
     *
     * A background build asks for every Movie's details, for its runtime, IMDb id and US date; an interactive one,
     * which a visitor waits for, asks only for the hero's, and leaves Coming soon, which needs a US date for each
     * Movie, to the full build. A Movie whose details can't be had keeps the previous build's, if it had any. The
     * calls are made one after another, and once the profile's time budget is spent no more are made: the sections
     * not yet read keep their previous versions, and the Movies not yet detailed their previous details. That keeps
     * a background build inside its job's timeout even when every call times out.
     */
    public function build(CatalogProfile $profile): void
    {
        $context = new CatalogContext(config('catalog.language'), profile: $profile);
        $this->deadline = now()->addSeconds(config('catalog.discover.build_budget_seconds.'.strtolower($profile->name)))->getTimestamp();
        $previous = $this->read($context);
        $previousMovies = [];

        foreach ([...array_filter([$previous['hero']['movie'] ?? null]), ...array_merge(...array_map(fn (DiscoverSection $section): array => $section->movies, array_values($previous['sections'])))] as $movie) {
            $previousMovies[$movie->tmdbId] ??= $movie;
        }

        $previousGenreCollections = array_values(array_filter($previous['sections'], fn (DiscoverSection $section): bool => $section->kind === SectionKind::Genre));
        $genres = $this->genresOfTheDay();
        $sections = [];
        $hero = null;
        $seen = [];

        foreach ($this->queries($genres) as $query) {
            $genre = $query->genreId === null ? null : ['id' => $query->genreId, 'name' => $genres[$query->genreId]];

            $movies = null;

            if ($query->kind !== SectionKind::ComingSoon || $profile === CatalogProfile::Background) {
                try {
                    $movies = $query->kind === SectionKind::ComingSoon
                        ? $this->comingSoon($query, $seen, $previousMovies, $context)
                        : $this->collect($query, $seen, $context, $this->resolver($query, $profile, $previousMovies, $context));
                } catch (SourceUnavailable $exception) {
                    $this->misconfiguration->reportIfMisconfigured($exception, $context);
                }
            }

            if ($movies === null) {
                $carried = $genre === null
                    ? $previous['sections'][$query->key()] ?? null
                    : $this->carriedGenreCollection($previousGenreCollections, $genre['id'], $genres);

                if ($carried !== null) {
                    $sections[] = $carried->without($seen);
                    $seen += array_fill_keys(array_map(fn (DiscoverMovie $movie): int => $movie->tmdbId, $carried->movies), true);
                }

                if ($query->kind === SectionKind::Trending) {
                    $hero = $previous['hero'];
                }

                continue;
            }

            $seen += array_fill_keys(array_map(fn (DiscoverMovie $movie): int => $movie->tmdbId, $movies), true);

            if ($query->kind === SectionKind::Trending) {
                $heroMovie = array_find($movies, fn (DiscoverMovie $movie): bool => $movie->backdropPath !== null && $movie->overview !== null);
                $hero = $heroMovie === null ? null : ['builtAt' => now()->getTimestamp(), 'movie' => $heroMovie];
            }

            $sections[] = new DiscoverSection($query->kind, now()->getTimestamp(), $movies, $genre);
        }

        Cache::put($this->key($context), [
            'hero' => $hero === null ? null : ['builtAt' => $hero['builtAt'], 'movie' => $hero['movie']->toArray()],
            'sections' => array_map(fn (DiscoverSection $section): array => $section->toArray(), $sections),
            'detailed' => $profile === CatalogProfile::Background,
        ], now()->addDays(config('catalog.discover.stale_after_days')));
    }

    /** What the stored payload has fresh enough to show, leaving out empty sections. */
    public function stored(CatalogContext $context): Discover
    {
        $payload = $this->read($context);

        return new Discover(
            $payload['hero']['movie'] ?? null,
            array_values(array_filter($payload['sections'], fn (DiscoverSection $section): bool => $section->movies !== [])),
            $payload['detailed'],
        );
    }

    public function key(CatalogContext $context): string
    {
        return $this->keys->build('discover', [], $context);
    }

    /**
     * The stored hero and sections that are fresh enough to show, with sections keyed by `DiscoverSection::key()` in
     * page order. Coming soon leaves out the Movies whose US date has come.
     *
     * @return array{hero: array{builtAt: int, movie: DiscoverMovie}|null, sections: array<string, DiscoverSection>, detailed: bool}
     */
    private function read(CatalogContext $context): array
    {
        $payload = Cache::get($this->key($context));
        $payload = is_array($payload) ? $payload : [];
        $heroMovie = DiscoverMovie::fromArray($payload['hero']['movie'] ?? null);
        $heroBuiltAt = $payload['hero']['builtAt'] ?? null;
        $sections = [];

        foreach (is_array($payload['sections'] ?? null) ? $payload['sections'] : [] as $data) {
            $section = DiscoverSection::fromArray($data);

            if ($section !== null && $this->isFresh($section->builtAt)) {
                $sections[$section->key()] = $section->kind === SectionKind::ComingSoon
                    ? $section->filter(fn (DiscoverMovie $movie): bool => $movie->opensOn !== null && $movie->opensOn > now()->utc()->toDateString())
                    : $section;
            }
        }

        return [
            'hero' => $heroMovie !== null && is_int($heroBuiltAt) && $this->isFresh($heroBuiltAt) ? ['builtAt' => $heroBuiltAt, 'movie' => $heroMovie] : null,
            'sections' => $sections,
            'detailed' => ($payload['detailed'] ?? false) === true,
        ];
    }

    /**
     * The day's sections, in page order: Trending, Popular, Coming soon, Top rated, then one per genre collection.
     *
     * @param  array<int, string>  $genres  the day's genres, by genre id
     * @return list<SectionQuery>
     */
    private function queries(array $genres): array
    {
        $today = now()->utc();

        return [
            SectionQuery::of(SectionKind::Trending),
            SectionQuery::of(SectionKind::Popular),
            SectionQuery::comingSoon($today->copy()->addDay()->toDateString(), $today->copy()->addDays(config('catalog.discover.coming_soon.days_ahead'))->toDateString()),
            SectionQuery::of(SectionKind::TopRated),
            ...array_map(fn (int $genreId): SectionQuery => SectionQuery::genre($genreId, config('catalog.discover.genre_collections.minimum_votes')), array_keys($genres)),
        ];
    }

    /**
     * The day's genres for the collections: `per_day` consecutive genres of the configured list, starting
     * `per_day` further along it each day (UTC), so the pick is the same all day and changes the next.
     *
     * @return array<int, string> genre names by genre id, in page order
     */
    private function genresOfTheDay(): array
    {
        $genres = [];

        foreach (config()->array('catalog.discover.genre_collections.genres') as $id => $name) {
            if (is_int($id) && is_string($name)) {
                $genres[$id] = $name;
            }
        }

        $ids = array_keys($genres);
        $perDay = min(config('catalog.discover.genre_collections.per_day'), count($ids));
        $day = intdiv(now()->utc()->startOfDay()->getTimestamp(), 86400);
        $picked = [];

        for ($slot = 0; $slot < $perDay; $slot++) {
            $id = $ids[($day * $perDay + $slot) % count($ids)];
            $picked[$id] = $genres[$id];
        }

        return $picked;
    }

    /**
     * What a failed genre collection keeps: the previous build's collection for the same genre, else the one in
     * its place, unless another of the day's collections is for that genre.
     *
     * @param  list<DiscoverSection>  $previousCollections  in page order
     * @param  array<int, string>  $genres  the day's genres, by genre id
     */
    private function carriedGenreCollection(array $previousCollections, int $genreId, array $genres): ?DiscoverSection
    {
        $sameGenre = array_find($previousCollections, fn (DiscoverSection $section): bool => $section->genre['id'] === $genreId);
        $samePlace = $previousCollections[array_search($genreId, array_keys($genres), true)] ?? null;

        return $sameGenre ?? ($samePlace !== null && ! isset($genres[$samePlace->genre['id']]) ? $samePlace : null);
    }

    /**
     * How a section turns a listing into a Movie. A background build details every Movie, leaving out one the
     * source no longer has; an interactive one details only Trending's first Movie with a backdrop and an
     * overview that the source still has, the hero.
     *
     * @param  array<int, DiscoverMovie>  $previousMovies
     * @return Closure(MovieListing): ?DiscoverMovie
     */
    private function resolver(SectionQuery $query, CatalogProfile $profile, array $previousMovies, CatalogContext $context): Closure
    {
        if ($profile === CatalogProfile::Background) {
            return fn (MovieListing $listing): ?DiscoverMovie => $this->detailed($listing, $previousMovies, $context);
        }

        $heroFound = $query->kind !== SectionKind::Trending;

        return function (MovieListing $listing) use (&$heroFound, $previousMovies, $context): ?DiscoverMovie {
            if ($heroFound || $listing->backdropPath === null || $listing->overview === null) {
                return $this->undetailed($listing, $previousMovies);
            }

            $movie = $this->detailed($listing, $previousMovies, $context);
            $heroFound = $movie !== null;

            return $movie;
        };
    }

    /**
     * Coming soon's Movies: those whose US date falls in the query's window, looked up one Movie at a time, at
     * most `max_lookups` of them. A Movie whose details can't be had keeps its previous US date, if it had one.
     *
     * @param  array<int, true>  $seen  catalog ids already in an earlier section
     * @param  array<int, DiscoverMovie>  $previousMovies
     * @return list<DiscoverMovie>
     *
     * @throws SourceUnavailable when the list can't be read, or a failed lookup leaves the section empty
     */
    private function comingSoon(SectionQuery $query, array $seen, array $previousMovies, CatalogContext $context): array
    {
        $lookups = 0;
        $failedLookups = 0;

        $maximumLookups = config('catalog.discover.coming_soon.max_lookups');

        $movies = $this->collect($query, $seen, $context, function (MovieListing $listing) use ($query, $previousMovies, $context, $maximumLookups, &$lookups, &$failedLookups): ?DiscoverMovie {
            if ($lookups >= $maximumLookups) {
                return null;
            }

            $lookups++;

            try {
                $details = $this->details($listing, $context);
                $movie = $details === null ? null : DiscoverMovie::fromListing($listing, $details);
            } catch (SourceUnavailable $exception) {
                $this->misconfiguration->reportIfMisconfigured($exception, $context);
                $failedLookups++;
                $movie = $this->undetailed($listing, $previousMovies);
            }

            return $movie?->opensOn !== null && $movie->opensOn >= $query->releasedFrom && $movie->opensOn <= $query->releasedUntil ? $movie : null;
        }, isDone: function () use (&$lookups, $maximumLookups): bool {
            return $lookups >= $maximumLookups;
        });

        if ($movies === [] && $failedLookups > 0) {
            throw new SourceUnavailable('No Coming soon date could be looked up.');
        }

        return $movies;
    }

    /**
     * Up to a section's worth of new Movies with posters, reading the list page by page, in its order. A listing
     * the resolver passes over makes room for the next; no further page is read once `isDone` says so.
     *
     * @param  array<int, true>  $seen  catalog ids already in an earlier section
     * @param  Closure(MovieListing): ?DiscoverMovie  $resolve
     * @param  (Closure(): bool)|null  $isDone
     * @return list<DiscoverMovie>
     *
     * @throws SourceUnavailable when a page can't be read, or the build's time budget is spent
     */
    private function collect(SectionQuery $query, array $seen, CatalogContext $context, Closure $resolve, ?Closure $isDone = null): array
    {
        $wanted = config('catalog.discover.movies_per_section');
        $collected = [];
        $considered = [];
        $lastPage = min(config('catalog.discover.max_pages_per_section'), CatalogSource::LAST_SOURCE_PAGE);

        for ($page = 1; $page <= $lastPage && count($collected) < $wanted && ! ($isDone !== null && $isDone()); $page++) {
            $this->ensureTimeLeft();
            $sectionPage = $this->source->section($query, $page, $context);
            $lastPage = min($lastPage, $sectionPage->totalPages);

            foreach ($sectionPage->listings as $listing) {
                $this->ratings->record(RatingSourceName::Tmdb, TmdbRatingId::for(TitleKind::Movie, $listing->tmdbId), $listing->voteAverage, $listing->voteCount);

                if (count($collected) < $wanted && $listing->posterPath !== null && ! isset($seen[$listing->tmdbId]) && ! isset($considered[$listing->tmdbId])) {
                    $considered[$listing->tmdbId] = true;
                    $movie = $resolve($listing);

                    if ($movie !== null) {
                        $collected[$listing->tmdbId] = $movie;
                    }
                }
            }
        }

        return array_values($collected);
    }

    /**
     * The listing completed with the Movie's details. When the details can't be fetched, it keeps the previous
     * build's, if any; a Movie the source no longer has is left out.
     *
     * @param  array<int, DiscoverMovie>  $previousMovies
     */
    private function detailed(MovieListing $listing, array $previousMovies, CatalogContext $context): ?DiscoverMovie
    {
        try {
            $details = $this->details($listing, $context);
        } catch (SourceUnavailable $exception) {
            $this->misconfiguration->reportIfMisconfigured($exception, $context);

            return $this->undetailed($listing, $previousMovies);
        }

        return $details === null ? null : DiscoverMovie::fromListing($listing, $details);
    }

    /**
     * The Movie's details, or null when the source no longer has it.
     *
     * @throws SourceUnavailable when they can't be fetched, or the build's time budget is spent
     */
    private function details(MovieListing $listing, CatalogContext $context): ?MovieDetails
    {
        $this->ensureTimeLeft();

        return $this->source->movie($listing->tmdbId, $context);
    }

    /**
     * The listing with the previous build's details for the Movie, if it had any.
     *
     * @param  array<int, DiscoverMovie>  $previousMovies
     */
    private function undetailed(MovieListing $listing, array $previousMovies): DiscoverMovie
    {
        $movie = DiscoverMovie::fromListing($listing);
        $previous = $previousMovies[$listing->tmdbId] ?? null;

        return $previous === null ? $movie : $movie->withDetailsOf($previous);
    }

    /** @throws SourceUnavailable once the build's time budget is spent */
    private function ensureTimeLeft(): void
    {
        if (now()->getTimestamp() >= $this->deadline) {
            throw new SourceUnavailable('The Discover build has used its time budget.');
        }
    }

    private function isFresh(int $builtAt): bool
    {
        return $builtAt >= now()->subDays(config('catalog.discover.stale_after_days'))->getTimestamp();
    }

    /**
     * What Discover shows, the same for every visitor. It is read, never built, while it has something to show;
     * otherwise this request builds it, asking only for the hero's details, while other visitors wait for that
     * build up to a limit.
     */
    public function discover(): Discover
    {
        $context = new CatalogContext(config('catalog.language'));
        $discover = $this->stored($context);

        if (! $discover->isEmpty()) {
            return $discover;
        }

        try {
            Cache::lock($this->key($context).':build', 60)->block(config('catalog.discover.build_wait_seconds'), function () use ($context): void {
                if ($this->stored($context)->isEmpty()) {
                    $this->build(CatalogProfile::Interactive);
                }
            });
        } catch (LockTimeoutException) {
        }

        return $this->stored($context);
    }

    /**
     * Discover's stored Trending Movies, in rank order, without building the payload or asking the source: none
     * when nothing fresh enough is stored.
     *
     * @return list<DiscoverMovie>
     */
    public function storedTrending(): array
    {
        $trending = array_find($this->stored(new CatalogContext(config('catalog.language')))->sections, fn (DiscoverSection $section): bool => $section->kind === SectionKind::Trending);

        return $trending->movies ?? [];
    }
}
