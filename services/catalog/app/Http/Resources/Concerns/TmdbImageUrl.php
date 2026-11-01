<?php

namespace App\Http\Resources\Concerns;

trait TmdbImageUrl
{
    /**
     * Full TMDB image URL for a stored path, in the size configured under services.tmdb.<kind>_size.
     */
    protected function imageUrl(?string $path, string $kind): ?string
    {
        if (! $path) {
            return null;
        }

        return rtrim(config('services.tmdb.image_url'), '/').'/'.config("services.tmdb.{$kind}_size").$path;
    }
}
