<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CalendarLinkResource extends JsonResource
{
    public function toArray($request): array
    {
        return ['url' => $this->resource];
    }
}
