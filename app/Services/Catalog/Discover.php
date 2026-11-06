<?php

namespace App\Services\Catalog;

/**
 * What Discover shows, the same for every visitor: the hero and the sections fresh enough to show, in page order.
 * It isn't `detailed` until a background build has added every Movie's details.
 */
readonly class Discover
{
    /** @param  list<DiscoverSection>  $sections */
    public function __construct(
        public ?DiscoverMovie $hero,
        public array $sections,
        public bool $detailed,
    ) {}

    public function isEmpty(): bool
    {
        return $this->sections === [];
    }

    /** @return list<DiscoverMovie> every Movie shown, the hero first, each once */
    public function movies(): array
    {
        $movies = [];

        foreach ([...array_filter([$this->hero]), ...array_merge(...array_map(fn (DiscoverSection $section): array => $section->movies, $this->sections))] as $movie) {
            $movies[$movie->tmdbId] ??= $movie;
        }

        return array_values($movies);
    }

    /** @return list<int> the catalog id of every Movie shown */
    public function catalogIds(): array
    {
        return array_map(fn (DiscoverMovie $movie): int => $movie->tmdbId, $this->movies());
    }
}
