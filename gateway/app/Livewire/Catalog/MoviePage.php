<?php

namespace App\Livewire\Catalog;

use App\Livewire\Concerns\WithServiceFallback;
use App\Services\Clients\CatalogClient;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Component;

class MoviePage extends Component
{
    use WithServiceFallback;

    public string $id;

    public function render(CatalogClient $catalog): View
    {
        // An unknown movie gives Catalog's 404, which renders the 404 page.
        $movie = $this->fromService('movie', fn () => $catalog->movie($this->id));

        return view('livewire.catalog.movie-page', [
            'movie' => $movie,
            'upcoming' => $movie !== null && $this->isUpcoming($movie['release_date']),
        ])->title($movie['title'] ?? 'Movie');
    }

    private function isUpcoming(?string $releaseDate): bool
    {
        return $releaseDate !== null && $releaseDate > Carbon::today('UTC')->toDateString();
    }
}
