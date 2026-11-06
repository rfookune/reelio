<?php

namespace App\Services\Catalog\Sources;

use App\Services\Catalog\CatalogContext;

interface CatalogSource
{
    public function movie(int $tmdbId, CatalogContext $context): ?MovieDetails;
}
