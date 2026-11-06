<?php

namespace App\Services\Catalog\Sources;

use App\Services\Catalog\CatalogContext;
use App\Services\Catalog\Enums\SectionKind;

interface CatalogSource
{
    public function movie(int $tmdbId, CatalogContext $context): ?MovieDetails;

    /** One page of a list, in the source's order. A missing page is a failure, not an empty answer. */
    public function section(SectionKind $kind, int $page, CatalogContext $context): SectionPage;
}
