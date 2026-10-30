<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TokenResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'token' => $this->token,
            'expires_at' => $this->expiresAt->toIso8601ZuluString(),
            'user' => new UserResource($this->user),
        ];
    }
}
