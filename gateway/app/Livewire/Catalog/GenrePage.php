<?php

namespace App\Livewire\Catalog;

use App\Livewire\Concerns\WithServiceFallback;
use App\Services\Clients\CatalogClient;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

class GenrePage extends Component
{
    use WithServiceFallback;

    public const SORTS = ['popularity' => 'Most popular', 'vote_average' => 'Best rated'];

    private const MAX_PAGE = 500;

    #[Locked]
    public string $id;

    #[Url(except: 'popularity')]
    public string $sort = 'popularity';

    #[Url(except: 1)]
    public int $page = 1;

    public function updatedSort(): void
    {
        $this->page = 1;
    }

    public function goToPage(int $page): void
    {
        $this->page = max(1, min($page, self::MAX_PAGE));
    }

    public function render(CatalogClient $catalog): View
    {
        $this->page = max(1, min($this->page, self::MAX_PAGE));
        $sort = array_key_exists($this->sort, self::SORTS) ? $this->sort : array_key_first(self::SORTS);

        $result = $this->fromService('movies', fn () => $catalog->genreMovies($this->id, $sort, $this->page));

        if ($result !== null && $result['data'] === [] && $this->page > $result['meta']['last_page']) {
            $this->page = max(1, $result['meta']['last_page']);
            $result = $this->fromService('movies', fn () => $catalog->genreMovies($this->id, $sort, $this->page));
        }

        $genre = collect($this->fromService('genres', fn () => $catalog->genres(), []))->firstWhere('id', $this->id);

        return view('livewire.catalog.genre-page', [
            'name' => $genre['name'] ?? 'Genre',
            'movies' => $result['data'] ?? [],
            'lastPage' => $result['meta']['last_page'] ?? 1,
            'total' => $result['meta']['total'] ?? 0,
        ])->title($genre['name'] ?? 'Genre');
    }
}
