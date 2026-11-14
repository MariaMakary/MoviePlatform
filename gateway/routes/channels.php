<?php

use Illuminate\Support\Facades\Broadcast;

/*
 * Private channels, authorized by POST /broadcasting/auth with the session user (the "broadcast" guard, see
 * AppServiceProvider). Social broadcasts on room.{id} and movie-night.{id}, Notification on user.{id}.
 * Room and movie night channels are added with their features (chat, voting).
 */

Broadcast::channel('user.{id}', fn ($user, $id) => (string) $user->id === (string) $id, ['guards' => ['broadcast']]);
