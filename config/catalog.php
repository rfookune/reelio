<?php

return [
    'language' => env('CATALOG_LANGUAGE', 'en-US'),
    'image_base_url' => env('TMDB_IMAGE_BASE_URL', 'https://image.tmdb.org/t/p/'),
    'tmdb' => [
        'base_url' => 'https://api.themoviedb.org/3/',
        'token' => env('TMDB_READ_TOKEN', ''),
    ],
];
