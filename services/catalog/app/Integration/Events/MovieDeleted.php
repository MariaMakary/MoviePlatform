<?php

namespace App\Integration\Events;

final class MovieDeleted
{
    public const ROUTING_KEY = 'movie.deleted';

    public static function data(string $movieId): array
    {
        return ['movie_id' => $movieId];
    }
}
