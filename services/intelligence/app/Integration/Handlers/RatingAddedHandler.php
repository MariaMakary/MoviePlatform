<?php

namespace App\Integration\Handlers;

use App\Services\TasteProfileService;
use MoviePlatform\Common\Integration\EventHandler;

class RatingAddedHandler implements EventHandler
{
    public function __construct(private TasteProfileService $profiles) {}

    public function handle(array $data, array $envelope): void
    {
        $this->profiles->rated($data['user_id'], $data['movie_id'], (int) $data['score']);
    }
}
