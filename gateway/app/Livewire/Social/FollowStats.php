<?php

namespace App\Livewire\Social;

use App\Livewire\Concerns\WithServiceFallback;
use App\Services\Clients\SocialClient;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/** Followers and following counts of one user, linking to the lists. Reloads when a follow button changes. */
class FollowStats extends Component
{
    use WithServiceFallback;

    #[Locked]
    public string $userId;

    #[On('follow-changed')]
    public function followChanged(): void {}

    public function render(SocialClient $social): View
    {
        return view('livewire.social.follow-stats', [
            'stats' => $this->fromService('stats', fn () => $social->followStats($this->userId)),
        ]);
    }
}
