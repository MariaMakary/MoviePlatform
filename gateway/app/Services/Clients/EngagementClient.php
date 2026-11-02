<?php

namespace App\Services\Clients;

class EngagementClient extends BaseClient
{
    // Engagement answers the "contains" endpoints for at most this many ids per call.
    private const MAX_IDS = 100;

    protected string $service = 'engagement';

    public function watchlist(int $page = 1, int $perPage = 20): array
    {
        return $this->get('/api/watchlist', ['page' => $page, 'per_page' => $perPage]);
    }

    public function addToWatchlist(string $movieId): array
    {
        return $this->post('/api/watchlist', ['movie_id' => $movieId])['data'];
    }

    public function removeFromWatchlist(string $movieId): void
    {
        $this->delete('/api/watchlist/'.rawurlencode($movieId));
    }

    /** The ids among $movieIds that are in the user's watchlist, in as few calls as possible. */
    public function watchlistContains(array $movieIds): array
    {
        $found = [];

        foreach (array_chunk(array_values(array_unique($movieIds)), self::MAX_IDS) as $chunk) {
            $found = [...$found, ...$this->get('/api/watchlist/contains', ['ids' => implode(',', $chunk)])['data']];
        }

        return $found;
    }
}
