<?php

return [
    'azure_language' => [
        'key' => secret('azure_language_key', ''),
        'endpoint' => env('AZURE_LANGUAGE_ENDPOINT', ''),
        'api_version' => '2023-04-01',
        'timeout' => 10,
    ],

    'weather' => [
        'open_meteo_url' => env('OPEN_METEO_URL', 'https://api.open-meteo.com/v1'),
        'nominatim_url' => env('NOMINATIM_URL', 'https://nominatim.openstreetmap.org'),
        'user_agent' => 'KAIA-MoviePlatform/1.0 (Antonine University student project)',
        'timeout' => 5,
        'weather_minutes' => 30,
        'city_days' => 7,
    ],
];
