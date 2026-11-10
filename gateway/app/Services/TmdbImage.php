<?php

namespace App\Services;

class TmdbImage
{
    public static function url(?string $path, string $size = 'w342'): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        if (! str_starts_with($path, '/')) {
            return $path;
        }

        return rtrim(config('services.tmdb.image_url'), '/').'/'.$size.$path;
    }
}
