<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class SentimentResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'label' => $this->resource['label']->value,
            'score' => $this->resource['score'],
        ];
    }
}
