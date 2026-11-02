<?php

namespace App\Services\Clients;

class EngagementClient extends BaseClient
{
    // Engagement answers the "contains" endpoints for at most this many ids per call.
    private const MAX_IDS = 100;

    protected string $service = 'engagement';

    /** {average, count, my_score}; my_score is null for guests. */
    public function ratingSummary(string $movieId): array
    {
        return $this->get('/api/movies/'.rawurlencode($movieId).'/rating-summary')['data'];
    }

    public function rate(string $movieId, int $score): array
    {
        return $this->post('/api/ratings', ['movie_id' => $movieId, 'score' => $score])['data'];
    }

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
        return $this->contains('/api/watchlist/contains', $movieIds);
    }

    public function history(int $page = 1, int $perPage = 20): array
    {
        return $this->get('/api/history', ['page' => $page, 'per_page' => $perPage]);
    }

    /** Also removes the movie from the watchlist. $watchedAt is UTC ISO-8601; null means now. */
    public function markWatched(string $movieId, ?string $watchedAt = null): array
    {
        return $this->post('/api/history', array_filter(['movie_id' => $movieId, 'watched_at' => $watchedAt]))['data'];
    }

    /** Movie id => latest watched_at, for the watched movies among $movieIds. */
    public function historyContains(array $movieIds): array
    {
        return array_column($this->contains('/api/history/contains', $movieIds), 'watched_at', 'movie_id');
    }

    private function contains(string $path, array $movieIds): array
    {
        $found = [];

        foreach (array_chunk(array_values(array_unique($movieIds)), self::MAX_IDS) as $chunk) {
            $found = [...$found, ...$this->get($path, ['ids' => implode(',', $chunk)])['data']];
        }

        return $found;
    }
}
