<?php

namespace App\Services\Catalog;

use App\Services\Catalog\Enums\CatalogProfile;
use App\Services\Catalog\Enums\SectionKind;
use App\Services\Catalog\Sources\CatalogSource;
use App\Services\Catalog\Sources\MovieListing;
use App\Services\Catalog\Sources\SourceUnavailable;
use App\Services\Ratings\Enums\RatingSourceName;
use App\Services\Ratings\RatingStore;
use Illuminate\Support\Facades\Cache;

/**
 * Builds and stores the Discover payload, the same for every visitor: the hero and each section's Movies, in page
 * order. The Catalog reads it through `stored()`; only `build()` asks the source for it.
 */
class DiscoverBuilder
{
    /** TMDB serves no list page past this one. */
    private const LAST_SOURCE_PAGE = 500;

    /** When the build in progress stops asking the source, as a timestamp. */
    private int $deadline = 0;

    public function __construct(
        private CatalogSource $source,
        private CacheKey $keys,
        private RatingStore $ratings,
        private MisconfigurationReporter $misconfiguration,
    ) {}

    /**
     * Rebuilds the payload. Sections are filled in page order, each Movie only in the earliest section that has
     * it, leaving out Movies without a poster; a later section fills up from its next pages. The hero, the
     * highest-ranked Trending Movie with a backdrop and an overview, stays in Trending, so Trending keeps its true
     * ranks. A section or hero that can't be rebuilt keeps its previous version while it is fresh enough. Every
     * listed Movie's TMDB score is recorded, with or without a Movie row.
     *
     * A background build asks for every Movie's details, for its runtime and IMDb id; an interactive one, which a
     * visitor waits for, asks only for the hero's. A Movie whose details can't be had keeps the previous build's,
     * if it had any. The calls are made one after another, and once the profile's time budget is spent no more are
     * made: the sections not yet read keep their previous versions, and the Movies not yet detailed their previous
     * details. The hero's details are asked for once, and reused for its Trending row. That keeps a background build inside its job's timeout even when every call times out.
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

        $sections = [];
        $hero = null;
        $seen = [];

        foreach (SectionKind::cases() as $kind) {
            try {
                $listings = $this->collect($kind, $seen, $context);
            } catch (SourceUnavailable $exception) {
                $this->misconfiguration->reportIfMisconfigured($exception, $context);
                $carried = $previous['sections'][$kind->value] ?? null;

                if ($carried !== null) {
                    $sections[] = $carried->without($seen);
                    $seen += array_fill_keys(array_map(fn (DiscoverMovie $movie): int => $movie->tmdbId, $carried->movies), true);
                }

                if ($kind === SectionKind::Trending) {
                    $hero = $previous['hero'];
                }

                continue;
            }

            $seen += array_fill_keys(array_map(fn (MovieListing $listing): int => $listing->tmdbId, $listings), true);

            if ($kind === SectionKind::Trending) {
                $hero = $this->hero($listings, $previousMovies, $context);
            }

            $heroMovie = $hero['movie'] ?? null;
            $movies = $profile === CatalogProfile::Background
                ? array_values(array_filter(array_map(fn (MovieListing $listing): ?DiscoverMovie => $listing->tmdbId === $heroMovie?->tmdbId ? $heroMovie : $this->detailed($listing, $previousMovies, $context), $listings)))
                : array_map(fn (MovieListing $listing): DiscoverMovie => $this->undetailed($listing, $previousMovies), $listings);
            $sections[] = new DiscoverSection($kind, now()->getTimestamp(), $movies);
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
     * The stored hero and sections that are fresh enough to show, with sections keyed by kind in page order.
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
                $sections[$section->kind->value] = $section;
            }
        }

        return [
            'hero' => $heroMovie !== null && is_int($heroBuiltAt) && $this->isFresh($heroBuiltAt) ? ['builtAt' => $heroBuiltAt, 'movie' => $heroMovie] : null,
            'sections' => $sections,
            'detailed' => ($payload['detailed'] ?? false) === true,
        ];
    }

    /**
     * The highest-ranked Trending Movie with a backdrop and an overview that the source still has.
     *
     * @param  list<MovieListing>  $listings
     * @param  array<int, DiscoverMovie>  $previousMovies
     * @return array{builtAt: int, movie: DiscoverMovie}|null
     */
    private function hero(array $listings, array $previousMovies, CatalogContext $context): ?array
    {
        foreach ($listings as $listing) {
            $movie = $listing->backdropPath !== null && $listing->overview !== null ? $this->detailed($listing, $previousMovies, $context) : null;

            if ($movie !== null) {
                return ['builtAt' => now()->getTimestamp(), 'movie' => $movie];
            }
        }

        return null;
    }

    /**
     * Up to a section's worth of new Movies with posters, reading the list page by page, in its order.
     *
     * @param  array<int, true>  $seen  catalog ids already in an earlier section
     * @return list<MovieListing>
     */
    private function collect(SectionKind $kind, array $seen, CatalogContext $context): array
    {
        $wanted = config('catalog.discover.movies_per_section');
        $collected = [];
        $lastPage = min(config('catalog.discover.max_pages_per_section'), self::LAST_SOURCE_PAGE);

        for ($page = 1; $page <= $lastPage && count($collected) < $wanted; $page++) {
            $this->ensureTimeLeft();
            $sectionPage = $this->source->section($kind, $page, $context);
            $lastPage = min($lastPage, $sectionPage->totalPages);

            foreach ($sectionPage->listings as $listing) {
                $this->ratings->record(RatingSourceName::Tmdb, (string) $listing->tmdbId, $listing->voteAverage, $listing->voteCount);

                if (count($collected) < $wanted && $listing->posterPath !== null && ! isset($seen[$listing->tmdbId]) && ! isset($collected[$listing->tmdbId])) {
                    $collected[$listing->tmdbId] = $listing;
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
            $this->ensureTimeLeft();
            $details = $this->source->movie($listing->tmdbId, $context);
        } catch (SourceUnavailable $exception) {
            $this->misconfiguration->reportIfMisconfigured($exception, $context);

            return $this->undetailed($listing, $previousMovies);
        }

        return $details === null ? null : DiscoverMovie::fromListing($listing, $details);
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
}
