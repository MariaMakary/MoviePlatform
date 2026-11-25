<?php

namespace App\Livewire\Admin;

use App\Exceptions\ServiceRequestException;
use App\Livewire\Concerns\WithServiceFallback;
use App\Services\Clients\CatalogClient;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Movies · Admin')]
class Movies extends Component
{
    use WithServiceFallback;

    #[Url(except: '')]
    public string $q = '';

    #[Url(except: 1)]
    public int $page = 1;

    #[Locked]
    public ?string $editingId = null;

    public string $title = '';

    public string $overview = '';

    public string $release_date = '';

    public string $runtime = '';

    public string $poster_path = '';

    public array $genre_ids = [];

    public string $tmdbId = '';

    #[Locked]
    public ?array $deleting = null;

    public function updatedQ(): void
    {
        $this->page = 1;
    }

    public function goToPage(int $page): void
    {
        $this->page = max(1, $page);
    }

    public function edit(string $id, CatalogClient $catalog): void
    {
        $this->resetErrorBag();
        unset($this->unavailable['save']);

        $movie = collect($this->fromService('movies', fn () => $catalog->adminMovies($this->q, $this->page)['data'], []))->firstWhere('id', $id);

        if ($movie === null) {
            return;
        }

        $this->editingId = $movie['id'];
        $this->title = $movie['title'];
        $this->overview = (string) $movie['overview'];
        $this->release_date = (string) $movie['release_date'];
        $this->runtime = $movie['runtime'] === null ? '' : (string) $movie['runtime'];
        $this->poster_path = (string) $movie['poster_path'];
        $this->genre_ids = $movie['genre_ids'];

        $this->dispatch('open-modal', name: 'edit-movie');
    }

    public function save(CatalogClient $catalog)
    {
        if ($this->editingId === null) {
            return null;
        }

        $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'overview' => ['nullable', 'string', 'max:5000'],
            'release_date' => ['nullable', 'date_format:Y-m-d'],
            'runtime' => ['nullable', 'integer', 'between:1,1000'],
            'poster_path' => ['nullable', 'string', 'max:255', 'regex:#^/[A-Za-z0-9_.-]+$#'],
            'genre_ids' => ['array'],
            'genre_ids.*' => ['uuid', 'distinct'],
        ], [
            'poster_path.regex' => 'Use a TMDB image path such as /abc123.jpg.',
        ]);

        try {
            $movie = $this->fromService('save', fn () => $catalog->updateMovie($this->editingId, [
                'title' => trim($this->title),
                'overview' => $this->blankToNull($this->overview),
                'release_date' => $this->blankToNull($this->release_date),
                'runtime' => $this->runtime === '' ? null : (int) $this->runtime,
                'poster_path' => $this->blankToNull($this->poster_path),
                'genre_ids' => array_values($this->genre_ids),
            ]));
        } catch (ServiceRequestException $e) {
            return $this->backToList($e->getMessage(), 'error');
        }

        return $movie === null ? null : $this->backToList("“{$movie['title']}” is saved.");
    }

    public function import(CatalogClient $catalog)
    {
        unset($this->unavailable['import']);
        $this->validate(['tmdbId' => ['required', 'integer', 'min:1', 'max:2147483647']], [], ['tmdbId' => 'TMDB id']);

        $movie = $this->fromService('import', function () use ($catalog) {
            try {
                return $catalog->importMovie((int) $this->tmdbId);
            } catch (ValidationException $e) {
                $this->addError('tmdbId', $e->validator->errors()->first('tmdb_id') ?: $e->getMessage());

                return null;
            }
        });

        return $movie === null ? null : $this->backToList("“{$movie['title']}” is imported.");
    }

    public function confirmDelete(string $id, string $title): void
    {
        unset($this->unavailable['delete']);
        $this->deleting = ['id' => $id, 'title' => $title];
        $this->dispatch('open-modal', name: 'delete-movie');
    }

    public function delete(CatalogClient $catalog)
    {
        if ($this->deleting === null) {
            return null;
        }

        try {
            $done = $this->fromService('delete', function () use ($catalog) {
                $catalog->deleteMovie($this->deleting['id']);

                return true;
            });
        } catch (ServiceRequestException $e) {
            return $this->backToList($e->getMessage(), 'error');
        }

        return $done ? $this->backToList("“{$this->deleting['title']}” is deleted.") : null;
    }

    public function render(CatalogClient $catalog): View
    {
        $this->page = max(1, $this->page);

        $result = $this->fromService('movies', fn () => $catalog->adminMovies(trim($this->q), $this->page));

        return view('livewire.admin.movies', [
            'movies' => $result['data'] ?? [],
            'meta' => $result['meta'] ?? ['current_page' => 1, 'last_page' => 1, 'total' => 0],
            'genres' => $this->editingId === null ? [] : $this->fromService('genres', fn () => $catalog->genres(), []),
            'imageBase' => rtrim((string) config('services.tmdb.image_url'), '/').'/w185',
        ]);
    }

    private function backToList(string $message, string $type = 'success')
    {
        session()->flash($type, $message);

        return $this->redirectRoute('admin.movies', array_filter(['q' => $this->q, 'page' => $this->page > 1 ? $this->page : null]));
    }

    private function blankToNull(string $value): ?string
    {
        return trim($value) === '' ? null : trim($value);
    }
}
