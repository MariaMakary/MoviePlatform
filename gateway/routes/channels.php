<?php

use App\Exceptions\ServiceRequestException;
use App\Exceptions\ServiceUnavailable;
use App\Services\Clients\SocialClient;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Str;

/*
 * Private channels, authorized by POST /broadcasting/auth with the session user (the "broadcast" guard, see
 * AppServiceProvider). Guests are refused on every channel. Social broadcasts on room.{id} and movie-night.{id},
 * Notification on user.{id}.
 */

Broadcast::channel('room.{id}', fn ($user, $id) => Str::isUuid($id), ['guards' => ['broadcast']]);

Broadcast::channel('movie-night.{id}', function ($user, $id) {
    if (! Str::isUuid($id)) {
        return false;
    }

    try {
        app(SocialClient::class)->movieNight($id);
    } catch (ServiceRequestException|ServiceUnavailable) {
        return false;
    }

    return true;
}, ['guards' => ['broadcast']]);

Broadcast::channel('user.{id}', fn ($user, $id) => (string) $user->id === (string) $id, ['guards' => ['broadcast']]);
