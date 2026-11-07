<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PrivacyResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'user_id' => $this->user_id,
            'show_reviews' => $this->show_reviews,
            'show_ratings' => $this->show_ratings,
            'show_lists' => $this->show_lists,
            'show_history' => $this->show_history,
        ];
    }
}
