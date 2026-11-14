<?php

return [
    'azure_language' => [
        'key' => secret('azure_language_key', ''),
        'endpoint' => env('AZURE_LANGUAGE_ENDPOINT', ''),
        'api_version' => '2023-04-01',
        'timeout' => 10,
    ],

    // Catalog's /api/internal/* routes, called with the service token (config/internal.php from packages/common).
    'catalog' => [
        'url' => env('CATALOG_URL', 'http://127.0.0.1:8002'),
        'timeout' => 5,
    ],
];
