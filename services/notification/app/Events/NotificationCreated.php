<?php

namespace App\Events;

use App\Models\Notification;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class NotificationCreated implements ShouldBroadcastNow
{
    public function __construct(public readonly Notification $notification) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("user.{$this->notification->user_id}");
    }

    public function broadcastAs(): string
    {
        return 'NotificationCreated';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->notification->id,
            'type' => $this->notification->type->value,
            'title' => $this->notification->title,
            'created_at' => $this->notification->created_at?->toIso8601ZuluString(),
        ];
    }
}
