<?php

namespace App\Integration\Handlers;

use App\Services\RecommendationService;
use App\Services\TasteProfileService;
use MoviePlatform\Common\Integration\EventHandler;

class UserDeletedHandler implements EventHandler
{
    public function __construct(private TasteProfileService $profiles, private RecommendationService $recommendations) {}

    public function handle(array $data, array $envelope): void
    {
        $this->profiles->forgetUser($data['user_id']);
        $this->recommendations->forgetUser($data['user_id']);
    }
}
