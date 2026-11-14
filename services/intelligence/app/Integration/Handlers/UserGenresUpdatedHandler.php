<?php

namespace App\Integration\Handlers;

use App\Services\TasteProfileService;
use MoviePlatform\Common\Integration\EventHandler;

class UserGenresUpdatedHandler implements EventHandler
{
    public function __construct(private TasteProfileService $profiles) {}

    public function handle(array $data, array $envelope): void
    {
        $this->profiles->setFavoriteGenres($data['user_id'], (array) $data['genre_ids']);
    }
}
