<?php

namespace App\Integration\Events;

use App\Models\User;

final class UserBlocked
{
    public const ROUTING_KEY = 'user.blocked';

    public static function data(User $user): array
    {
        return ['user_id' => $user->id];
    }
}
