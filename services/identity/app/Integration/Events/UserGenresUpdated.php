<?php

namespace App\Integration\Events;

use App\Models\User;

final class UserGenresUpdated
{
    public const ROUTING_KEY = 'user.genres_updated';

    public static function data(User $user): array
    {
        return [
            'user_id' => $user->id,
            'genre_ids' => $user->genres->pluck('genre_id')->all(),
        ];
    }
}
