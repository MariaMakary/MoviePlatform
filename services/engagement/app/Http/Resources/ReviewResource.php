<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ReviewResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'movie_id' => $this->movie_id,
            'body' => $this->body,
            'author_score' => $this->author_score === null ? null : (int) $this->author_score,
            'sentiment_label' => $this->sentiment_label?->value,
            'sentiment_score' => $this->sentiment_score,
            'created_at' => $this->created_at?->toIso8601ZuluString(),
            'updated_at' => $this->updated_at?->toIso8601ZuluString(),
        ];
    }
}
