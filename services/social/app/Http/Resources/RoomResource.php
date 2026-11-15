<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class RoomResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'movie_id' => $this->movie_id,
            'name' => $this->name,
            'last_message_at' => $this->last_message_at ? Carbon::parse($this->last_message_at, 'UTC')->toIso8601ZuluString() : null,
            'created_at' => $this->created_at?->toIso8601ZuluString(),
        ];
    }
}
