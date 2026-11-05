<?php

namespace App\Catalog;

interface CatalogSource
{
    public function movie(int $tmdbId, CatalogContext $context): ?MovieDetails;
}
