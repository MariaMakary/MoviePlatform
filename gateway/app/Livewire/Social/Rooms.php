<?php

namespace App\Livewire\Social;

use App\Livewire\Concerns\WithServiceFallback;
use App\Services\Clients\SocialClient;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Discussions')]
class Rooms extends Component
{
    use WithServiceFallback;

    private const PER_PAGE = 20;

    #[Url(except: 1)]
    public int $page = 1;

    public function goToPage(int $page): void
    {
        $this->page = max(1, $page);
    }

    public function render(SocialClient $social): View
    {
        $this->page = max(1, $this->page);

        $result = $this->fromService('rooms', fn () => $social->rooms($this->page, self::PER_PAGE));

        return view('livewire.social.rooms', [
            'rooms' => $result['data'] ?? [],
            'lastPage' => $result['meta']['last_page'] ?? 1,
        ]);
    }
}
