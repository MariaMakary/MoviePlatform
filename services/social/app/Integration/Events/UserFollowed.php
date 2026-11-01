<?php

namespace App\Integration\Events;

use App\Models\Follow;

final class UserFollowed
{
    public const ROUTING_KEY = 'user.followed';

    public static function data(Follow $follow, string $followerName): array
    {
        return [
            'follower_id' => $follow->follower_id,
            'follower_name' => $followerName,
            'following_id' => $follow->following_id,
        ];
    }
}
