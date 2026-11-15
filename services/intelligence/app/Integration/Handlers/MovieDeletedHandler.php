<?php

namespace App\Integration\Handlers;

use App\Services\RecommendationService;
use App\Services\TasteProfileService;
use MoviePlatform\Common\Integration\EventHandler;

class MovieDeletedHandler implements EventHandler
{
    public function __construct(private TasteProfileService $profiles, private RecommendationService $recommendations) {}

    public function handle(array $data, array $envelope): void
    {
        $this->profiles->forgetMovie($data['movie_id']);
        $this->recommendations->forgetMovie($data['movie_id']);
    }
}
