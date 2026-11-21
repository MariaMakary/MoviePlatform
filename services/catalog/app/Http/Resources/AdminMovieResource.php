<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\TmdbImageUrl;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminMovieResource extends JsonResource
{
    use TmdbImageUrl;

    public function toArray($request): array
    {
        $genres = $this->genres->sortBy('name')->values();

        return [
            'id' => $this->id,
            'tmdb_id' => $this->tmdb_id,
            'title' => $this->title,
            'overview' => $this->overview,
            'poster_path' => $this->poster_path,
            'poster_url' => $this->imageUrl($this->poster_path, 'poster'),
            'release_date' => $this->release_date?->toDateString(),
            'runtime' => $this->runtime,
            'genre_ids' => $genres->pluck('id')->all(),
            'genres' => $genres->pluck('name')->all(),
            'is_edited' => $this->is_edited,
            'synced_at' => $this->synced_at?->toIso8601ZuluString(),
            'updated_at' => $this->updated_at?->toIso8601ZuluString(),
        ];
    }
}
