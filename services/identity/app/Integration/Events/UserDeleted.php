<?php

namespace App\Integration\Events;

use App\Models\User;

final class UserDeleted
{
    public const ROUTING_KEY = 'user.deleted';

    public static function data(User $user): array
    {
        return ['user_id' => $user->id];
    }
}
