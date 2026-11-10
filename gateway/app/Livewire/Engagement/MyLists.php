<?php

namespace App\Livewire\Engagement;

use App\Livewire\Concerns\WithServiceFallback;
use App\Services\Clients\EngagementClient;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('My lists')]
class MyLists extends Component
{
    use WithServiceFallback;

    private const PER_PAGE = 20;

    #[Url(except: 1)]
    public int $page = 1;

    public function goToPage(int $page): void
    {
        $this->page = max(1, $page);
    }

    public function render(EngagementClient $engagement): View
    {
        $this->page = max(1, $this->page);
        $result = $this->fromService('lists', fn () => $engagement->lists($this->page, self::PER_PAGE));

        return view('livewire.engagement.my-lists', [
            'lists' => $result['data'] ?? [],
            'lastPage' => $result['meta']['last_page'] ?? 1,
            'total' => $result['meta']['total'] ?? 0,
        ]);
    }
}
