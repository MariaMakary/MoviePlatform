<?php

namespace App\Integration\Handlers;

use App\Services\ActivityService;
use MoviePlatform\Common\Integration\EventHandler;

/** RatingAdded, ReviewCreated, ReviewUpdated, MovieWatched and ListCreated: each adds or updates a feed entry. */
class ActivityHandler implements EventHandler
{
    public function __construct(private ActivityService $activities) {}

    public function handle(array $data, array $envelope): void
    {
        $this->activities->record($envelope['event'], $data, $envelope);
    }
}
