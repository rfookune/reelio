<?php

namespace App\Catalog;

use App\Catalog\Enums\CatalogProfile;

readonly class CatalogContext
{
    public function __construct(
        public string $language = 'en-US',
        public ?string $region = null,
        public CatalogProfile $profile = CatalogProfile::Interactive,
    ) {}
}
