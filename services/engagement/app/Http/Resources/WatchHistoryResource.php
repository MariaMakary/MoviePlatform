<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class WatchHistoryResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'movie_id' => $this->movie_id,
            'watched_at' => $this->watched_at?->toIso8601ZuluString(),
        ];
    }
}
