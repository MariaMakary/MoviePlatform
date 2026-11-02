<?php

namespace App\Services\Clients;

class CatalogClient extends BaseClient
{
    protected string $service = 'catalog';

    /** Movie cards in the order of $ids (at most 100); unknown ids are skipped. */
    public function movies(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return $this->get('/api/movies', ['ids' => implode(',', $ids)])['data'];
    }

    public function movie(string $id): array
    {
        return $this->get('/api/movies/'.rawurlencode($id))['data'];
    }

    public function popular(int $page = 1): array
    {
        return $this->get('/api/movies/popular', ['page' => $page]);
    }

    public function upcoming(int $page = 1): array
    {
        return $this->get('/api/movies/upcoming', ['page' => $page]);
    }
}
