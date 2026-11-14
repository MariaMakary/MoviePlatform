<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class FollowStatusResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'user_id' => $this->resource['user_id'],
            'is_following' => $this->resource['is_following'],
        ];
    }
}
