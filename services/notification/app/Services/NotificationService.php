<?php

namespace App\Services;

use App\Events\NotificationCreated;
use App\Models\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use MoviePlatform\Common\Notifications\NotificationType;
use Throwable;

class NotificationService
{
    /**
     * Saves the notification and, once the surrounding transaction (the event consumer's) commits, broadcasts
     * NotificationCreated on user.{id}. A rolled back handler broadcasts nothing; Reverb down only loses the
     * live update (the Gateway's polling still shows it).
     */
    public function notify(string $userId, NotificationType $type, string $title, string $body, ?array $data = null): Notification
    {
        $notification = Notification::create([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'data' => $data,
        ]);

        DB::afterCommit(fn () => $this->broadcast($notification));

        return $notification;
    }

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

    private function broadcast(Notification $notification): void
    {
        try {
            event(new NotificationCreated($notification));
        } catch (Throwable $e) {
            Log::warning('NotificationCreated was not broadcast', ['notification_id' => $notification->id, 'error' => $e->getMessage()]);
        }
    }
}
