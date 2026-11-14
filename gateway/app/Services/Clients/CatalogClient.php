<?php

namespace App\Services\Clients;

class CatalogClient extends BaseClient
{
    protected string $service = 'catalog';

    private const MAX_IDS = 100;

    /** Movie cards in the order of $ids; unknown ids are skipped. */
    public function movies(array $ids): array
    {
        $cards = [];

        foreach (array_chunk(array_values($ids), self::MAX_IDS) as $chunk) {
            $cards = [...$cards, ...$this->get('/api/movies', ['ids' => implode(',', $chunk)])['data']];
        }

        return $cards;
    }

    public function movie(string $id): array
    {
        return $this->get('/api/movies/'.rawurlencode($id))['data'];
    }

    /** Up to 12 movie cards similar to $id; an empty list when there are none. */
    public function similar(string $id): array
    {
        return $this->get('/api/movies/'.rawurlencode($id).'/similar')['data'];
    }

    public function popular(int $page = 1): array
    {
        return $this->get('/api/movies/popular', ['page' => $page]);
    }

    public function upcoming(int $page = 1): array
    {
        return $this->get('/api/movies/upcoming', ['page' => $page]);
    }

    /** $filters: q, genre, year, sort (all optional). Movie cards with the pagination meta. */
    public function search(array $filters, int $page = 1): array
    {
        return $this->get('/api/movies/search', [...$filters, 'page' => $page]);
    }

    /** [['id', 'name']], from task 1173. */
    public function genres(): array
    {
        return $this->get('/api/genres')['data'];
    }

    public function genreMovies(string $genreId, string $sort, int $page = 1): array
    {
        return $this->get('/api/genres/'.rawurlencode($genreId).'/movies', ['sort' => $sort, 'page' => $page]);
    }

    public function person(string $id): array
    {
        return $this->get('/api/people/'.rawurlencode($id))['data'];
    }
}
