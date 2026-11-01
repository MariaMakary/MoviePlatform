<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/** One user in a followers or following list: their id and when the follow started. */
class FollowUserResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'user_id' => $this->user_id,
            'followed_at' => $this->created_at?->toIso8601ZuluString(),
        ];
    }
}
