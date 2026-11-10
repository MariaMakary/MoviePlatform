<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\TmdbImageUrl;
use Illuminate\Http\Resources\Json\JsonResource;

class MovieCardResource extends JsonResource
{
    use TmdbImageUrl;

    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'poster_url' => $this->imageUrl($this->poster_path, 'poster'),
            'year' => $this->release_date?->year,
            'release_date' => $this->release_date?->toDateString(),
            'vote_average' => $this->vote_average === null ? null : (float) $this->vote_average,
            'genres' => $this->genres->pluck('name')->sort()->values()->all(),
        ];
    }
}
