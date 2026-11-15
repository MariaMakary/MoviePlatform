<?php

namespace App\Integration\Handlers;

use App\Services\RecommendationService;
use App\Services\TasteProfileService;
use MoviePlatform\Common\Integration\EventHandler;

class MovieWatchedHandler implements EventHandler
{
    public function __construct(private TasteProfileService $profiles, private RecommendationService $recommendations) {}

    public function handle(array $data, array $envelope): void
    {
        $this->profiles->watched($data['user_id'], $data['movie_id']);
        $this->recommendations->refreshAfterCommit($data['user_id']);
    }
}
