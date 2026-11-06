<?php

namespace App\Services\Catalog\Sources;

use App\Services\Catalog\CatalogContext;

class FakeCatalogSource implements CatalogSource
{
    /** @param list<MovieDetails> $movies */
    public function __construct(public array $movies = [], public bool $failing = false) {}

    public function movie(int $tmdbId, CatalogContext $context): ?MovieDetails
    {
        if ($this->failing) {
            throw new SourceUnavailable('The Catalog source is unavailable.');
        }

        foreach ($this->movies as $movie) {
            if ($movie->tmdbId === $tmdbId) {
                return $movie;
            }
        }

        return null;
    }
}
