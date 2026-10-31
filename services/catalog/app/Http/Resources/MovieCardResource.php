<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class MovieCardResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'poster_url' => $this->posterUrl(),
            'year' => $this->release_date?->year,
            'release_date' => $this->release_date?->toDateString(),
            'vote_average' => $this->vote_average === null ? null : (float) $this->vote_average,
            'genres' => $this->genres->pluck('name')->sort()->values()->all(),
        ];
    }

    private function posterUrl(): ?string
    {
        if (! $this->poster_path) {
            return null;
        }

        return rtrim(config('services.tmdb.image_url'), '/').'/'.config('services.tmdb.poster_size').$this->poster_path;
    }
}
