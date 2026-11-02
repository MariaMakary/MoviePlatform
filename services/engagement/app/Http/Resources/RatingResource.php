<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class RatingResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'movie_id' => $this->movie_id,
            'score' => $this->score,
            'created_at' => $this->created_at?->toIso8601ZuluString(),
        ];
    }
}
