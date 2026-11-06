<?php

namespace App\Catalog\Sources;

use App\Catalog\CatalogContext;

interface CatalogSource
{
    public function movie(int $tmdbId, CatalogContext $context): ?MovieDetails;
}
