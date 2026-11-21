<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class GenreCountResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'genre' => $this->resource['genre'],
            'count' => $this->resource['count'],
        ];
    }
}
