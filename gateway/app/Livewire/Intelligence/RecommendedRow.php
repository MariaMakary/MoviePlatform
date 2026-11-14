<?php

namespace App\Livewire\Intelligence;

use App\Exceptions\ServiceRequestException;
use App\Livewire\Concerns\WithServiceFallback;
use App\Services\AuthSession;
use App\Services\Clients\CatalogClient;
use App\Services\Clients\IntelligenceClient;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Home row "Recommended for you" (logged-in users). With no picks yet, or Intelligence unavailable, it shows
 * "Popular now" from Catalog instead, so the homepage never has an empty or broken section.
 */
class RecommendedRow extends Component
{
    use WithServiceFallback;

    public function placeholder(): string
    {
        return '<div class="h-72 animate-pulse rounded-2xl bg-white/[0.03]" aria-hidden="true"></div>';
    }

    public function render(AuthSession $auth, IntelligenceClient $intelligence, CatalogClient $catalog): View
    {
        $user = $auth->user();

        if ($user === null) {
            return view('livewire.intelligence.recommended-row', ['mode' => 'hidden', 'items' => []]);
        }

        $recommended = $this->recommended($intelligence, $catalog, $user['id']);

        if ($recommended !== []) {
            return view('livewire.intelligence.recommended-row', ['mode' => 'recommended', 'items' => $recommended]);
        }

        $popular = $this->fromService('catalog', fn () => $catalog->popular(1)['data'] ?? []);

        return view('livewire.intelligence.recommended-row', [
            'mode' => $popular === null ? 'unavailable' : 'popular',
            'items' => array_map(fn (array $card) => ['card' => $card, 'reason' => null], $popular ?? []),
        ]);
    }

    /** [{card, reason}] in Intelligence's order; [] when there are none, or Intelligence or Catalog cannot give them. */
    private function recommended(IntelligenceClient $intelligence, CatalogClient $catalog, string $userId): array
    {
        try {
            $picks = $this->fromService('recommendations', fn () => $intelligence->recommendations($userId), []);
        } catch (ServiceRequestException) {
            $picks = [];
        }

        if ($picks === []) {
            return [];
        }

        $cards = array_column($this->fromService('catalog', fn () => $catalog->movies(array_column($picks, 'movie_id')), []), null, 'id');

        return array_values(array_filter(array_map(
            fn (array $pick) => isset($cards[$pick['movie_id']]) ? ['card' => $cards[$pick['movie_id']], 'reason' => $pick['reason']] : null,
            $picks,
        )));
    }
}
