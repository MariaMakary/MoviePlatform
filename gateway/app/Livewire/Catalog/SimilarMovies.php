<?php

namespace App\Livewire\Catalog;

use App\Livewire\Concerns\WithServiceFallback;
use App\Services\Clients\CatalogClient;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/** The "Similar movies" row of the movie page. Catalog or TMDB down shows the unavailable box in its place. */
class SimilarMovies extends Component
{
    use WithServiceFallback;

    #[Locked]
    public string $movieId;

    public function render(CatalogClient $catalog): View
    {
        return view('livewire.catalog.similar-movies', [
            'movies' => $this->fromService('similar', fn () => $catalog->similar($this->movieId), []),
        ]);
    }
}
