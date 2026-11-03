<?php

namespace App\Integration\Events;

use App\Models\MovieList;

final class ListCreated
{
    public const ROUTING_KEY = 'list.created';

    public static function data(MovieList $list): array
    {
        return [
            'list_id' => $list->id,
            'user_id' => $list->user_id,
            'title' => $list->title,
            'is_public' => $list->is_public,
        ];
    }
}
