<?php

namespace App\Http\Resources;

/** A review as admins see it: the public fields plus why it may be hidden. */
class AdminReviewResource extends ReviewResource
{
    public function toArray($request): array
    {
        return [
            ...parent::toArray($request),
            'is_hidden' => $this->is_hidden,
            'author_blocked' => $this->author_blocked,
        ];
    }
}
