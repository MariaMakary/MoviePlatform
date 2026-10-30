<?php

namespace App\Services;

use App\Models\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class NotificationService
{
    public function listFor(string $userId): Builder
    {
        return Notification::ownedBy($userId)->latest()->orderByDesc('id');
    }

    public function unreadCount(string $userId): int
    {
        return Notification::ownedBy($userId)->unread()->count();
    }

    public function markRead(string $userId, string $id): Notification
    {
        if (! Str::isUuid($id)) {
            throw (new ModelNotFoundException)->setModel(Notification::class);
        }

        $notification = Notification::ownedBy($userId)->findOrFail($id);

        if ($notification->read_at === null) {
            $notification->update(['read_at' => Carbon::now()]);
        }

        return $notification;
    }

    public function markAllRead(string $userId): void
    {
        Notification::ownedBy($userId)->unread()->update(['read_at' => Carbon::now()]);
    }
}
