<?php

namespace App\Integration\Clients;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/** Catalog's internal routes (docs/api/catalog.md), with the X-Service-Token header. */
class CatalogClient
{
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

    private function request(): PendingRequest
    {
        return Http::baseUrl(config('services.catalog.url'))
            ->acceptJson()
            ->timeout(config('services.catalog.timeout'))
            ->withHeaders(['X-Service-Token' => (string) config('internal.service_token')]);
    }

    private function unavailable(string $path, ?int $status, ?string $error = null): CatalogUnavailable
    {
        Log::warning('Catalog unavailable', ['path' => $path, 'status' => $status, 'error' => $error]);

        return new CatalogUnavailable('Catalog is unavailable.');
    }
}
