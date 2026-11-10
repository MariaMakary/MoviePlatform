<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'avatar' => $this->avatar,
            'bio' => $this->bio,
            'role' => $this->role->value,
            'genre_ids' => $this->whenLoaded('genres', fn () => $this->genres->pluck('genre_id')->all()),
        ];
    }
}
