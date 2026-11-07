<?php

return [
    'azure_language' => [
        'key' => secret('azure_language_key', ''),
        'endpoint' => env('AZURE_LANGUAGE_ENDPOINT', ''),
        'api_version' => '2023-04-01',
        'timeout' => 10,
    ],
];
