<?php

namespace App\Services;

use Illuminate\Support\Facades\Route;
use MoviePlatform\Common\Notifications\NotificationType;

class NotificationLink
{
    public static function for(array $notification): ?string
    {
        [$route, $key] = match (NotificationType::tryFrom($notification['type'] ?? '')) {
            NotificationType::NewFollower => ['profiles.show', 'follower_id'],
            NotificationType::MovieNightInvite,
            NotificationType::MovieNightInviteAnswered,
            NotificationType::MovieNightFinalized => ['movie-nights.show', 'movie_night_id'],
            default => [null, null],
        };
        $id = $key ? ($notification['data'][$key] ?? null) : null;

        return $route && $id && Route::has($route) ? route($route, $id) : null;
    }
}
