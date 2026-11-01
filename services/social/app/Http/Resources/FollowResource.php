<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class FollowResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'follower_id' => $this->follower_id,
            'following_id' => $this->following_id,
            'created_at' => $this->created_at?->toIso8601ZuluString(),
        ];
    }
}
