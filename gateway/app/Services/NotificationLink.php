<?php

namespace App\Services;

use Illuminate\Support\Facades\Route;

class NotificationLink
{
    /** Page each notification type opens: [route name, key in the notification's data passed as the route parameter]. */
    private const ROUTES = [
        'new_follower' => ['profiles.show', 'follower_id'],
        'movie_night_invite' => ['movie-nights.show', 'movie_night_id'],
        'movie_night_invite_answered' => ['movie-nights.show', 'movie_night_id'],
        'movie_night_finalized' => ['movie-nights.show', 'movie_night_id'],
    ];

    /** The page a notification opens, or null when it has none (or that page isn't built yet). */
    public static function for(array $notification): ?string
    {
        [$route, $key] = self::ROUTES[$notification['type']] ?? [null, null];
        $id = $notification['data'][$key] ?? null;

        return $route && $id && Route::has($route) ? route($route, $id) : null;
    }
}
