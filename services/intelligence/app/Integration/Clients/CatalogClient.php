<?php

namespace App\Integration\Clients;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/** Catalog's internal routes (docs/api/catalog.md), with the X-Service-Token header. */
class CatalogClient
{
    // Catalog's limits on the internal routes (docs/api/catalog.md).
    private const MAX_GENRES = 10;

    private const MAX_IDS = 100;

    /**
     * The movie's Catalog genre, cast and director ids, or null when Catalog no longer has the movie.
     *
     * @return array{genre_ids: string[], cast_ids: string[], director_ids: string[]}|null
     *
     * @throws CatalogUnavailable
     */
    public function movieFeatures(string $movieId): ?array
    {
        $path = '/api/internal/movies/'.rawurlencode($movieId).'/features';

        try {
            $response = $this->request()->get($path);
        } catch (ConnectionException $e) {
            throw $this->unavailable($path, null, $e->getMessage());
        }

        if ($response->status() === 404) {
            return null;
        }

        if (! $response->successful() || ! is_array($response->json('data'))) {
            throw $this->unavailable($path, $response->status());
        }

        return [
            'genre_ids' => (array) $response->json('data.genre_ids', []),
            'cast_ids' => (array) $response->json('data.cast_ids', []),
            'director_ids' => (array) $response->json('data.director_ids', []),
        ];
    }

    /** The most popular local movies in any of these genres, as movie cards. */
    public function discover(array $genreIds, array $excludeIds = [], int $limit = 50): array
    {
        return $this->list('/api/internal/discover', array_filter([
            'genre_ids' => implode(',', array_slice($genreIds, 0, self::MAX_GENRES)),
            'exclude_ids' => implode(',', array_slice($excludeIds, 0, self::MAX_IDS)),
            'limit' => $limit,
        ]));
    }

    /** TMDB's similar movies as cards; [] when Catalog no longer has the movie. */
    public function similar(string $movieId): array
    {
        return $this->list('/api/internal/movies/'.rawurlencode($movieId).'/similar', [], emptyOn404: true);
    }

    /** Movie cards for these ids (at most 100), in their order. */
    public function movies(array $ids): array
    {
        return $ids === [] ? [] : $this->list('/api/internal/movies', ['ids' => implode(',', array_slice($ids, 0, self::MAX_IDS))]);
    }

    /** Genre name => Catalog genre id. A public route: sent without the service token. */
    public function genreIdsByName(): array
    {
        return array_column($this->list('/api/genres', [], withToken: false), 'id', 'name');
    }

    private function list(string $path, array $query, bool $emptyOn404 = false, bool $withToken = true): array
    {
        try {
            $response = $this->request($withToken)->get($path, $query);
        } catch (ConnectionException $e) {
            throw $this->unavailable($path, null, $e->getMessage());
        }

        if ($emptyOn404 && $response->status() === 404) {
            return [];
        }

        if (! $response->successful() || ! is_array($response->json('data'))) {
            throw $this->unavailable($path, $response->status());
        }

        return $response->json('data');
    }

    private function request(bool $withToken = true): PendingRequest
    {
        $request = Http::baseUrl(config('services.catalog.url'))
            ->acceptJson()
            ->timeout(config('services.catalog.timeout'));

        return $withToken ? $request->withHeaders(['X-Service-Token' => (string) config('internal.service_token')]) : $request;
    }

    private function unavailable(string $path, ?int $status, ?string $error = null): CatalogUnavailable
    {
        Log::warning('Catalog unavailable', ['path' => $path, 'status' => $status, 'error' => $error]);

        return new CatalogUnavailable('Catalog is unavailable.');
    }
}
