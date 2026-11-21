<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserStatsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'total_users' => $this->resource['total_users'],
            'blocked_users' => $this->resource['blocked_users'],
            'admins' => $this->resource['admins'],
            'signups' => $this->resource['signups'],
        ];
    }
}
