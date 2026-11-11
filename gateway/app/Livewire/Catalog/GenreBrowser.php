<?php

namespace App\Livewire\Catalog;

use App\Exceptions\ServiceRequestException;
use App\Livewire\Concerns\WithServiceFallback;
use App\Services\Clients\CatalogClient;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class GenreBrowser extends Component
{
    use WithServiceFallback;

    #[Locked]
    public ?string $genreId = null;

    #[Locked]
    public array $movies = [];

    #[Locked]
    public int $page = 0;

    #[Locked]
    public int $lastPage = 1;

    public function select(string $genreId): void
    {
        $this->genreId = $this->genreId === $genreId ? null : $genreId;
        $this->movies = [];
        $this->page = 0;
        $this->lastPage = 1;
        unset($this->unavailable['movies']);
    }

    public function loadMore(CatalogClient $catalog): void
    {
        if ($this->genreId !== null && $this->page > 0 && $this->hasMore()) {
            $this->loadNextPage($catalog);
        }
    }

    public function hasMore(): bool
    {
        return $this->page < $this->lastPage;
    }

    public function placeholder(): string
    {
        return '<div></div>';
    }

    public function render(CatalogClient $catalog): View
    {
        $genres = $this->fromService('genres', fn () => $catalog->genres(), []);
        $selected = collect($genres)->firstWhere('id', $this->genreId);

        if ($selected !== null && $this->page === 0) {
            $this->loadNextPage($catalog);
        }

        return view('livewire.catalog.genre-browser', [
            'genres' => $genres,
            'selected' => $selected,
        ]);
    }

    private function loadNextPage(CatalogClient $catalog): void
    {
        try {
            $result = $this->fromService('movies', fn () => $catalog->genreMovies($this->genreId, 'popularity', $this->page + 1));
        } catch (ServiceRequestException $e) {
            $this->unavailable['movies'] = $e->service;

            return;
        }

        if ($result === null) {
            return;
        }

        $shown = array_column($this->movies, 'id');

        foreach ($result['data'] as $movie) {
            if (! in_array($movie['id'], $shown, true)) {
                $this->movies[] = $movie;
                $shown[] = $movie['id'];
            }
        }

        $this->page = $result['meta']['current_page'];
        $this->lastPage = $result['meta']['last_page'];
    }
}
