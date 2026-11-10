<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class WatchlistItemResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'movie_id' => $this->movie_id,
            'created_at' => $this->created_at?->toIso8601ZuluString(),
        ];
    }
}
