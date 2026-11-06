<?php

return [
    'sources' => [
        /*
         * IMDb can withdraw permission for its non-commercial dataset. Switching it off hides the IMDb rating
         * and its credit everywhere and stops its daily sync, leaving the other sources in place.
         */
        'imdb' => [
            'enabled' => (bool) env('RATINGS_IMDB_ENABLED', true),
            'dataset_url' => env('IMDB_RATINGS_DATASET_URL', 'https://datasets.imdbws.com/title.ratings.tsv.gz'),
        ],
        'tmdb' => [
            'enabled' => (bool) env('RATINGS_TMDB_ENABLED', true),
        ],
    ],
];
