<?php

namespace App\Catalog;

class CacheKey
{
    /** @param array<string, int|string|null> $parameters */
    public function build(string $kind, array $parameters, CatalogContext $context): string
    {
        ksort($parameters);

        return implode(':', ['catalog', 'v1', $kind, hash('sha256', json_encode($parameters, JSON_THROW_ON_ERROR)), $context->language, $context->region ?? '']);
    }
}
