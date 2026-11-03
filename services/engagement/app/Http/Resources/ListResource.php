<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ListResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'title' => $this->title,
            'description' => $this->description,
            'is_public' => $this->is_public,
            'contains_movie' => $this->when($this->contains_movie !== null, fn () => (bool) $this->contains_movie),
            'movie_ids' => $this->whenLoaded('items', fn () => $this->items->pluck('movie_id')->all()),
            'created_at' => $this->created_at?->toIso8601ZuluString(),
            'updated_at' => $this->updated_at?->toIso8601ZuluString(),
        ];
    }
}
