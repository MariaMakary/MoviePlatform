<?php

namespace App\Livewire\Notifications;

use App\Livewire\Concerns\ReadsNotifications;
use App\Livewire\Concerns\WithServiceFallback;
use App\Services\Clients\NotificationClient;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Notifications')]
class Index extends Component
{
    use ReadsNotifications;
    use WithServiceFallback;

    private const PER_PAGE = 20;

    #[Url(except: 1)]
    public int $page = 1;

    public function goToPage(int $page): void
    {
        $this->page = max(1, $page);
    }

    public function render(): View
    {
        $this->page = max(1, $this->page);

        $result = $this->fromService('notifications', fn () => app(NotificationClient::class)->notifications($this->page, self::PER_PAGE));

        return view('livewire.notifications.index', [
            'notifications' => $result['data'] ?? [],
            'lastPage' => $result['meta']['last_page'] ?? 1,
            'total' => $result['meta']['total'] ?? 0,
        ]);
    }
}
