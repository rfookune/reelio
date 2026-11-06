<?php

return [
    'language' => env('CATALOG_LANGUAGE', 'en-US'),
    'image_base_url' => env('TMDB_IMAGE_BASE_URL', 'https://image.tmdb.org/t/p/'),

    /*
     * The countries Reelio stores and shows Availability for. Adding one is a config change plus a backfill.
     */
    'supported_countries' => ['US', 'CA', 'GB', 'AU', 'NZ', 'IE', 'DE', 'FR', 'ES', 'IT'],

    /*
     * Where to watch shows this country until the visitor's country is resolved per request.
     */
    'default_country' => 'US',
    'tmdb' => [
        'base_url' => 'https://api.themoviedb.org/3/',
        'token' => env('TMDB_READ_TOKEN', ''),
    ],

    'details' => [
        'fresh_seconds' => 86400,
        'hard_lifetime_days' => 30,
    ],

    'creation_limit' => [
        'attempts' => 20,
        'decay_seconds' => 60,
    ],

    'guard' => [
        'failure_threshold' => 5,
        'breaker_open_seconds' => 30,
        'failure_memory_seconds' => 86400,
        'live_requests_per_second' => 15,
    ],
];
