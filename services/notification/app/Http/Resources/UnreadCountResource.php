<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UnreadCountResource extends JsonResource
{
    public function toArray($request): array
    {
        return ['count' => $this->resource];
    }
}
