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
        'hard_lifetime_days' => 30,
    ],

    'refresh' => [
        /*
         * A Movie page view refreshes the Movie in the request once its last sync is older than this.
         */
        'on_view_after_seconds' => 86400,
    ],

    'discover' => [
        'movies_per_section' => 10,

        /*
         * How many pages a section reads to fill up after the poster filter and the dedupe, at most. TMDB's own
         * cap of 500 pages is applied whatever this says.
         */
        'max_pages_per_section' => 3,

        /*
         * A section, or the hero, whose rebuild fails keeps its previous version this long, then is hidden.
         */
        'stale_after_days' => 7,

        /*
         * How long a build may keep asking the source, by profile. The calls are made one after another, so the
         * background budget leaves room for one more slow call (about 35 s with its retries) inside the job's 85 s
         * timeout; the interactive one keeps the first visitor's wait short.
         */
        'build_budget_seconds' => [
            'background' => 45,
            'interactive' => 6,
        ],

        /*
         * How long a first visitor waits for another visitor's build before the page renders without it.
         */
        'build_wait_seconds' => 10,
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
