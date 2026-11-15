<?php

namespace App\Integration\Handlers;

use App\Services\UserDataService;
use MoviePlatform\Common\Integration\EventHandler;

class UserUnblockedHandler implements EventHandler
{
    public function __construct(private UserDataService $users) {}

    public function handle(array $data, array $envelope): void
    {
        $this->users->setAuthorBlocked($data['user_id'], false);
    }
}
