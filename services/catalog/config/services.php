<?php

return [
    'tmdb' => [
        'key' => secret('tmdb_api_key', ''),
        'url' => env('TMDB_URL', 'https://api.themoviedb.org/3'),
        'language' => 'en-US',
        'region' => env('TMDB_REGION', 'US'),
        'timeout' => 5,
        'image_url' => env('TMDB_IMAGE_URL', 'https://image.tmdb.org/t/p'),
        'poster_size' => 'w342',
    ],
];
