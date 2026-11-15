<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class MessageSent implements ShouldBroadcastNow
{
    public function __construct(public readonly Message $message, public readonly string $userName) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("room.{$this->message->room_id}");
    }

    public function broadcastAs(): string
    {
        return 'MessageSent';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->message->id,
            'user_id' => $this->message->user_id,
            'user_name' => $this->userName,
            'body' => $this->message->body,
            'created_at' => $this->message->created_at?->toIso8601ZuluString(),
        ];
    }
}
