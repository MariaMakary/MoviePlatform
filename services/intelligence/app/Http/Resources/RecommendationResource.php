<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class RecommendationResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'movie_id' => $this->movie_id,
            'score' => $this->score,
            'reason' => $this->reason,
        ];
    }
}
