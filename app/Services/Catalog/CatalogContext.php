<?php

namespace App\Services\Catalog;

use App\Services\Catalog\Enums\CatalogProfile;

readonly class CatalogContext
{
    public function __construct(
        public string $language = 'en-US',
        public ?string $region = null,
        public CatalogProfile $profile = CatalogProfile::Interactive,
    ) {}
}
