<?php

namespace App\Livewire\Concerns;

use App\Exceptions\ServiceRequestException;
use App\Services\AuthSession;
use App\Services\Clients\NotificationClient;
use App\Services\NotificationLink;
use Illuminate\Support\Str;

/** Actions shared by the navbar bell and the notifications page. Needs WithServiceFallback. */
trait ReadsNotifications
{
    public function open(string $id): void
    {
        if (! Str::isUuid($id) || ! app(AuthSession::class)->check()) {
            return;
        }

        try {
            $notification = $this->fromService('notifications', fn () => app(NotificationClient::class)->markRead($id));
        } catch (ServiceRequestException) {
            return;
        }

        if ($notification && $link = NotificationLink::for($notification)) {
            $this->redirect($link);
        }
    }

    public function markAllAsRead(): void
    {
        if (app(AuthSession::class)->check()) {
            $this->fromService('notifications', fn () => app(NotificationClient::class)->markAllRead());
        }
    }
}
