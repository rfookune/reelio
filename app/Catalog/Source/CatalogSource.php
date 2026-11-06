<?php

namespace App\Catalog\Source;

use App\Catalog\CatalogContext;

interface CatalogSource
{
    public function movie(int $tmdbId, CatalogContext $context): ?MovieDetails;
}
