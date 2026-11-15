<?php

namespace App\Integration\Clients;

use MoviePlatform\Common\Integration\InternalClient;

class CatalogClient
{
    public function movieTitle(string $movieId): ?string
    {
        return InternalClient::to('catalog')->get('/api/internal/movies', ['ids' => $movieId])['data'][0]['title'] ?? null;
    }
}
