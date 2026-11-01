<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class FollowStatsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'followers' => $this->resource['followers'],
            'following' => $this->resource['following'],
            'is_following' => $this->resource['is_following'],
        ];
    }
}
