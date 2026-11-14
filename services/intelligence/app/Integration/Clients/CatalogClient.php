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

    public function movieFeatures(string $movieId): ?array
    {
        $features = InternalClient::to('catalog')->get('/api/internal/movies/'.rawurlencode($movieId).'/features')['data'] ?? null;

        if (! is_array($features)) {
            return null;
        }

        return [
            'genre_ids' => (array) ($features['genre_ids'] ?? []),
            'cast_ids' => (array) ($features['cast_ids'] ?? []),
            'director_ids' => (array) ($features['director_ids'] ?? []),
        ];
    }
}
