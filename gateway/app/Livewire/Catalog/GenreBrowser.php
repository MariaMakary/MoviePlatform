<?php

namespace App\Livewire\Catalog;

use App\Livewire\Concerns\WithServiceFallback;
use App\Services\Clients\CatalogClient;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class GenreBrowser extends Component
{
    use WithServiceFallback;

    public const SHOWN = 12;

    public ?string $genreId = null;

    public function select(string $genreId): void
    {
        $this->genreId = $this->genreId === $genreId ? null : $genreId;
        unset($this->unavailable['movies']);
    }

    public function placeholder(): string
    {
        return '<div></div>';
    }

    public function render(CatalogClient $catalog): View
    {
        $genres = $this->fromService('genres', fn () => $catalog->genres(), []);
        $selected = collect($genres)->firstWhere('id', $this->genreId);

        $result = $selected === null ? null
            : $this->fromService('movies', fn () => $catalog->genreMovies($selected['id'], 'popularity'));

        return view('livewire.catalog.genre-browser', [
            'genres' => $genres,
            'selected' => $selected,
            'movies' => array_slice($result['data'] ?? [], 0, self::SHOWN),
            'total' => $result['meta']['total'] ?? 0,
        ]);
    }
}
