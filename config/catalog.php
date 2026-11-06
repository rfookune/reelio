<?php

return [
    'language' => env('CATALOG_LANGUAGE', 'en-US'),
    'image_base_url' => env('TMDB_IMAGE_BASE_URL', 'https://image.tmdb.org/t/p/'),

    /*
     * The countries Reelio stores and shows Availability for. Adding one is a config change plus a backfill.
     */
    'supported_countries' => ['US', 'CA', 'GB', 'AU', 'NZ', 'IE', 'DE', 'FR', 'ES', 'IT'],

    /*
     * Shown when the visitor hasn't chosen a country and the edge detected none, or one that isn't supported.
     */
    'default_country' => 'US',

    /*
     * The request header the edge puts the visitor's detected country in. Detection is never stored.
     */
    'country_header' => env('CATALOG_COUNTRY_HEADER', 'CF-IPCountry'),
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
