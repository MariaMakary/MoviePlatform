<?php

return [
    'tmdb' => [
        'key' => secret('tmdb_api_key', ''),
        'url' => env('TMDB_URL', 'https://api.themoviedb.org/3'),
        'language' => 'en-US',
        'timeout' => 5,
    ],
];
