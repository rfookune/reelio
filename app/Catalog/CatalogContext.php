<?php

namespace App\Catalog;

readonly class CatalogContext
{
    public function __construct(public string $language = 'en-US', public ?string $region = null) {}
}
