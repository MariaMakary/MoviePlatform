<?php

namespace App\Http\Resources;

use App\Enums\ParticipantStatus;
use Illuminate\Http\Resources\Json\JsonResource;

class MovieNightResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'host_id' => $this->host_id,
            'title' => $this->title,
            'description' => $this->description,
            'scheduled_at' => $this->scheduled_at?->toIso8601ZuluString(),
            'location' => $this->location,
            'meeting_link' => $this->meeting_link,
            'status' => $this->status->value,
            'winner_movie_id' => $this->winner_movie_id,
            'my_status' => ParticipantStatus::tryFrom((string) $this->my_status)?->value,
            'created_at' => $this->created_at?->toIso8601ZuluString(),
        ];
    }
}
