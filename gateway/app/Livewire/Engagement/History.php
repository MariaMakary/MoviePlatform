<?php

namespace App\Livewire\Engagement;

use App\Livewire\Concerns\WithServiceFallback;
use App\Services\Clients\CatalogClient;
use App\Services\Clients\EngagementClient;
use App\Services\DisplayTime;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('History')]
class History extends Component
{
    use WithServiceFallback;

    private const PER_PAGE = 20;

    #[Url(except: 1)]
    public int $page = 1;

    public function goToPage(int $page): void
    {
        $this->page = max(1, $page);
    }

    public function render(EngagementClient $engagement, CatalogClient $catalog): View
    {
        $this->page = max(1, $this->page);
        $result = $this->fromService('history', fn () => $engagement->history($this->page, self::PER_PAGE));
        $entries = $result['data'] ?? [];

        $cards = $this->fromService('catalog', fn () => $catalog->movies(array_column($entries, 'movie_id')), []);
        $movies = array_column($cards, null, 'id');

        // "November 2026" => entries of that month, newest first (the order Engagement sends them in).
        $months = [];
        foreach ($entries as $entry) {
            if (isset($movies[$entry['movie_id']])) {
                $month = DisplayTime::fromService($entry['watched_at'])->translatedFormat('F Y');
                $months[$month][] = ['entry' => $entry, 'movie' => $movies[$entry['movie_id']]];
            }
        }

        return view('livewire.engagement.history', [
            'months' => $months,
            'lastPage' => $result['meta']['last_page'] ?? 1,
            'total' => $result['meta']['total'] ?? 0,
        ]);
    }
}
