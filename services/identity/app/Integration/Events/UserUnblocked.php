<?php

namespace App\Integration\Events;

use App\Models\User;

final class UserUnblocked
{
    public const ROUTING_KEY = 'user.unblocked';

    public static function data(User $user): array
    {
        return ['user_id' => $user->id];
    }
}
