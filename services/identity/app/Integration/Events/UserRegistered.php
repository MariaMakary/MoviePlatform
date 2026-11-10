<?php

namespace App\Integration\Events;

use App\Models\User;

final class UserRegistered
{
    public const ROUTING_KEY = 'user.registered';

    public static function data(User $user): array
    {
        return [
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ];
    }
}
