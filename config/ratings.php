<?php

return [
    'imdb' => [
        /*
         * IMDb can withdraw permission for its non-commercial dataset. Turning this off removes the IMDb
         * rating and its credit everywhere, leaving the TMDB rating in place.
         */
        'enabled' => (bool) env('RATINGS_IMDB_ENABLED', true),
        'dataset_url' => env('IMDB_RATINGS_DATASET_URL', 'https://datasets.imdbws.com/title.ratings.tsv.gz'),
    ],
];
