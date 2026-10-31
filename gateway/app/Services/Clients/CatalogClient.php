<?php

namespace App\Services\Clients;

class CatalogClient extends BaseClient
{
    protected string $service = 'catalog';

    public function popular(int $page = 1): array
    {
        return $this->get('/api/movies/popular', ['page' => $page]);
    }

    public function upcoming(int $page = 1): array
    {
        return $this->get('/api/movies/upcoming', ['page' => $page]);
    }
}
