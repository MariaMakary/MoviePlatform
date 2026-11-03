<?php

namespace App\Livewire\Catalog;

use App\Exceptions\ServiceRequestException;
use App\Livewire\Concerns\WithServiceFallback;
use App\Services\Clients\CatalogClient;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/** /search: every filter lives in the URL, so a copied link reopens the same search. */
#[Title('Search')]
class Search extends Component
{
    use WithServiceFallback;

    public const SORTS = ['popularity' => 'Most popular', 'vote_average' => 'Best rated', 'release_date' => 'Newest'];

    private const MIN_QUERY = 2;

    private const MAX_PAGE = 500;

    #[Url(except: '')]
    public string $q = '';

    #[Url(except: '')]
    public string $genre = '';

    #[Url(except: '')]
    public string $year = '';

    #[Url(except: 'popularity')]
    public string $sort = 'popularity';

    #[Url(except: 1)]
    public int $page = 1;

    // A new search starts again from the first page.
    public function updated(string $property): void
    {
        if ($property !== 'page') {
            $this->page = 1;
        }
    }

    public function goToPage(int $page): void
    {
        $this->page = max(1, min($page, self::MAX_PAGE));
    }

    public function render(CatalogClient $catalog): View
    {
        $this->page = max(1, min($this->page, self::MAX_PAGE));

        $result = $this->fromService('catalog', fn () => $catalog->search($this->filters(), $this->page));

        return view('livewire.catalog.search', [
            'movies' => $result['data'] ?? [],
            'lastPage' => $result['meta']['last_page'] ?? 1,
            'total' => $result['meta']['total'] ?? 0,
            'genres' => $result === null ? [] : $this->genres($catalog),
            'tooShort' => Str::length(trim($this->q)) === 1,
        ]);
    }

    /** Only valid values reach Catalog: a half-typed year or a 1-letter search is simply left out. */
    private function filters(): array
    {
        $year = filter_var($this->year, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1870, 'max_range' => 2100]]);

        return array_filter([
            'q' => Str::length(trim($this->q)) >= self::MIN_QUERY ? Str::limit(trim($this->q), 100, '') : null,
            'genre' => Str::isUuid($this->genre) ? $this->genre : null,
            'year' => $year === false ? null : $year,
            'sort' => array_key_exists($this->sort, self::SORTS) ? $this->sort : null,
        ]);
    }

    // The genre list comes from task 1173: until Catalog has it (404), the dropdown is hidden.
    private function genres(CatalogClient $catalog): array
    {
        try {
            return $this->fromService('genres', fn () => $catalog->genres(), []);
        } catch (ServiceRequestException) {
            return [];
        }
    }
}
