<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ActivityResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'type' => $this->type->value,
            'subject_id' => $this->subject_id,
            'movie_id' => $this->movie_id,
            'data' => (object) ($this->data ?? []),
            'created_at' => $this->created_at?->toIso8601ZuluString(),
        ];
    }
}
