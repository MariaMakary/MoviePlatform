<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\TmdbImageUrl;
use Illuminate\Http\Resources\Json\JsonResource;

class MovieDetailResource extends JsonResource
{
    use TmdbImageUrl;

    public const CAST_LIMIT = 15;

    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'overview' => $this->overview,
            'poster_url' => $this->imageUrl($this->poster_path, 'poster'),
            'backdrop_url' => $this->imageUrl($this->backdrop_path, 'backdrop'),
            'year' => $this->release_date?->year,
            'release_date' => $this->release_date?->toDateString(),
            'runtime' => $this->runtime,
            'vote_average' => $this->vote_average === null ? null : (float) $this->vote_average,
            'genres' => $this->genres->sortBy('name')->map(fn ($genre) => ['id' => $genre->id, 'name' => $genre->name])->values()->all(),
            'directors' => PersonCreditResource::collection($this->directors->sortBy('name')->values()),
            'cast' => PersonCreditResource::collection($this->cast->take(self::CAST_LIMIT)),
            'trailer_key' => $this->trailer_key,
        ];
    }
}
