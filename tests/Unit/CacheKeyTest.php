<?php

use App\Catalog\CacheKey;
use App\Catalog\CatalogContext;

test('catalog cache keys are versioned and independent of parameter order', function () {
    $keys = new CacheKey;
    $key = $keys->build('movie', ['id' => 157336, 'page' => 1], new CatalogContext('en-US'));

    expect($key)->toMatch('/^catalog:v1:movie:[a-f0-9]{64}:en-US:$/')
        ->toBe($keys->build('movie', ['page' => 1, 'id' => 157336], new CatalogContext('en-US')))
        ->not->toBe($keys->build('movie', ['id' => 42, 'page' => 1], new CatalogContext('en-US')))
        ->not->toBe($keys->build('search', ['id' => 157336, 'page' => 1], new CatalogContext('en-US')))
        ->not->toBe($keys->build('movie', ['id' => 157336, 'page' => 1], new CatalogContext('fr-FR')))
        ->not->toBe($keys->build('movie', ['id' => 157336, 'page' => 1], new CatalogContext('en-US', 'US')));
});
