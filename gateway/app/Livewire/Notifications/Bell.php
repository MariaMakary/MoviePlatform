<?php

namespace App\Livewire\Notifications;

use App\Exceptions\ServiceRequestException;
use App\Livewire\Concerns\ReadsNotifications;
use App\Livewire\Concerns\WithServiceFallback;
use App\Services\AuthSession;
use App\Services\Clients\NotificationClient;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class Bell extends Component
{
    use ReadsNotifications;
    use WithServiceFallback;

    private const LATEST = 5;

    public bool $expanded = false;

    /**
     * Notification broadcasts NotificationCreated on user.{id} once a notification is saved: re-render to get the
     * new count (and the list when open). The view polls every 30 seconds only while the WebSocket is not connected.
     */
    public function getListeners(): array
    {
        $userId = app(AuthSession::class)->user()['id'] ?? null;

        return $userId ? ["echo-private:user.{$userId},.NotificationCreated" => '$refresh'] : [];
    }

    public function toggle(): void
    {
        $this->expanded = ! $this->expanded;
    }

    public function close(): void
    {
        $this->expanded = false;
    }

    public function render(): View
    {
        $notifications = app(NotificationClient::class);
        $count = null;
        $latest = [];

        if (app(AuthSession::class)->check()) {
            try {
                $count = $this->fromService('notifications', fn () => $notifications->unreadCount());

                if ($this->expanded && $count !== null) {
                    $latest = $this->fromService('notifications', fn () => $notifications->notifications(1, self::LATEST)['data'], []);
                }
            } catch (ServiceRequestException) {
                $count = null;
            }
        }

        return view('livewire.notifications.bell', ['count' => $count, 'latest' => $latest]);
    }
}
