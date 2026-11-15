<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class VotesUpdated implements ShouldBroadcastNow
{
    public function __construct(public readonly string $movieNightId, public readonly array $totals) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("movie-night.{$this->movieNightId}");
    }

    public function broadcastAs(): string
    {
        return 'VotesUpdated';
    }

    public function broadcastWith(): array
    {
        return ['movie_night_id' => $this->movieNightId, 'totals' => $this->totals];
    }
}
