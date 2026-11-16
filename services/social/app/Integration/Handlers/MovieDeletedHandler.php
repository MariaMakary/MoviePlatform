<?php

namespace App\Integration\Handlers;

use App\Services\ActivityService;
use App\Services\RoomService;
use App\Services\VotingService;
use MoviePlatform\Common\Integration\EventHandler;

class MovieDeletedHandler implements EventHandler
{
    public function __construct(private VotingService $voting, private RoomService $rooms, private ActivityService $activities) {}

    public function handle(array $data, array $envelope): void
    {
        $this->voting->removeMovie($data['movie_id']);
        $this->rooms->removeMovie($data['movie_id']);
        $this->activities->removeMovie($data['movie_id']);
    }
}
