<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/** A list in the public browse page: no movie_ids, only a count and the first ones for the poster collage. */
class PublicListResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'title' => $this->title,
            'description' => $this->description,
            'movie_count' => (int) $this->movie_count,
            'first_movie_ids' => json_decode($this->first_movie_ids ?? '[]', true),
            'created_at' => $this->created_at?->toIso8601ZuluString(),
            'updated_at' => $this->updated_at?->toIso8601ZuluString(),
        ];
    }
}
