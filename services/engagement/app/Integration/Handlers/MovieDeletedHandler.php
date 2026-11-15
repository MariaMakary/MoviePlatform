<?php

namespace App\Integration\Handlers;

use App\Services\MovieDataService;
use MoviePlatform\Common\Integration\EventHandler;

class MovieDeletedHandler implements EventHandler
{
    public function __construct(private MovieDataService $movies) {}

    public function handle(array $data, array $envelope): void
    {
        $this->movies->deleteAll($data['movie_id']);
    }
}
