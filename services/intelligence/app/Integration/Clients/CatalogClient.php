<?php

namespace App\Integration\Clients;

use MoviePlatform\Common\Integration\InternalClient;

class CatalogClient
{
    private const MAX_GENRES = 10;

    private const MAX_IDS = 100;

    public function discover(array $genres, int $limit, array $excludeIds = []): array
    {
        return $this->catalog()->get('/api/internal/discover', array_filter([
            'genres' => implode(',', $genres),
            'exclude_ids' => implode(',', $excludeIds),
            'limit' => $limit,
        ], fn ($value) => $value !== ''))['data'] ?? [];
    }

    public function discoverByGenreIds(array $genreIds, array $excludeIds = [], int $limit = 50): array
    {
        return $this->catalog()->get('/api/internal/discover', array_filter([
            'genre_ids' => implode(',', array_slice($genreIds, 0, self::MAX_GENRES)),
            'exclude_ids' => implode(',', array_slice($excludeIds, 0, self::MAX_IDS)),
            'limit' => $limit,
        ], fn ($value) => $value !== ''))['data'] ?? [];
    }

    public function movieFeatures(string $movieId): ?array
    {
        $features = $this->catalog()->get('/api/internal/movies/'.rawurlencode($movieId).'/features')['data'] ?? null;

        if (! is_array($features)) {
            return null;
        }

        return [
            'genre_ids' => (array) ($features['genre_ids'] ?? []),
            'cast_ids' => (array) ($features['cast_ids'] ?? []),
            'director_ids' => (array) ($features['director_ids'] ?? []),
        ];
    }

    public function similar(string $movieId): array
    {
        return $this->catalog()->get('/api/internal/movies/'.rawurlencode($movieId).'/similar')['data'] ?? [];
    }

    public function movies(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return $this->catalog()->get('/api/internal/movies', ['ids' => implode(',', array_slice($ids, 0, self::MAX_IDS))])['data'] ?? [];
    }

    public function genreIdsByName(): array
    {
        return array_column($this->catalog()->get('/api/genres')['data'] ?? [], 'id', 'name');
    }

    private function catalog(): InternalClient
    {
        return InternalClient::to('catalog');
    }
}
