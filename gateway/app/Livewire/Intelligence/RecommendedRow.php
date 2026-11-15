<?php

namespace App\Livewire\Intelligence;

use App\Exceptions\ServiceRequestException;
use App\Livewire\Concerns\WithServiceFallback;
use App\Services\AuthSession;
use App\Services\Clients\CatalogClient;
use App\Services\Clients\IntelligenceClient;
use Illuminate\Contracts\View\View;
use Livewire\Component;

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

        return view('livewire.intelligence.recommended-row', [
            'items' => $user === null ? [] : $this->recommended($intelligence, $catalog, $user['id']),
            'loggedIn' => $user !== null,
        ]);
    }

    private function recommended(IntelligenceClient $intelligence, CatalogClient $catalog, string $userId): array
    {
        try {
            $picks = $this->fromService('recommendations', fn () => $intelligence->recommendations($userId), []);
        } catch (ServiceRequestException $e) {
            $this->unavailable['recommendations'] = $e->service;
            $picks = [];
        }

        if ($picks === []) {
            return [];
        }

        $cards = array_column($this->fromService('movies', fn () => $catalog->movies(array_column($picks, 'movie_id')), []), null, 'id');

        return array_values(array_filter(array_map(
            fn (array $pick) => isset($cards[$pick['movie_id']]) ? ['card' => $cards[$pick['movie_id']], 'reason' => $pick['reason']] : null,
            $picks,
        )));
    }
}
