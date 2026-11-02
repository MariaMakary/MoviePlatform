<?php

namespace App\Livewire\Engagement;

use App\Exceptions\ServiceUnavailable;
use App\Livewire\Concerns\WithServiceFallback;
use App\Services\Clients\CatalogClient;
use App\Services\Clients\EngagementClient;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('My watchlist')]
class Watchlist extends Component
{
    use WithServiceFallback;

    private const PER_PAGE = 20;

    #[Url(except: 1)]
    public int $page = 1;

    public ?string $error = null;

    public function goToPage(int $page): void
    {
        $this->page = max(1, $page);
    }

    public function remove(string $movieId, EngagementClient $engagement): void
    {
        try {
            $engagement->removeFromWatchlist($movieId);
        } catch (ServiceUnavailable) {
            $this->error = "Couldn't remove the movie. Try again.";

            return;
        }

        $this->error = null;
        $this->dispatch('watchlist-changed', movieId: $movieId, inWatchlist: false);
    }

    public function render(EngagementClient $engagement, CatalogClient $catalog): View
    {
        $this->page = max(1, $this->page);
        $result = $this->fromService('watchlist', fn () => $engagement->watchlist($this->page, self::PER_PAGE));

        // Removing the last movie of the last page: show the page before it.
        if ($result !== null && $result['data'] === [] && $this->page > $result['meta']['last_page']) {
            $this->page = max(1, $result['meta']['last_page']);
            $result = $this->fromService('watchlist', fn () => $engagement->watchlist($this->page, self::PER_PAGE));
        }

        $movieIds = array_column($result['data'] ?? [], 'movie_id');
        $cards = $this->fromService('catalog', fn () => $catalog->movies($movieIds), []);

        return view('livewire.engagement.watchlist', [
            'movies' => $cards,
            'lastPage' => $result['meta']['last_page'] ?? 1,
            'total' => $result['meta']['total'] ?? 0,
        ]);
    }
}
