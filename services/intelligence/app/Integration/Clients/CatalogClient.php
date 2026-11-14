<?php

namespace App\Integration\Clients;

use MoviePlatform\Common\Integration\InternalClient;

class CatalogClient
{
    public function discover(array $genres, int $limit, array $excludeIds = []): array
    {
        return InternalClient::to('catalog')->get('/api/internal/discover', array_filter([
            'genres' => implode(',', $genres),
            'exclude_ids' => implode(',', $excludeIds),
            'limit' => $limit,
        ], fn ($value) => $value !== ''))['data'] ?? [];
    }
}
