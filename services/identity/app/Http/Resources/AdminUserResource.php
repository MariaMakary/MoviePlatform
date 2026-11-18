<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/** A user as admins see them (user management, 1271): with email, role and blocked status. Admins only. */
class AdminUserResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'avatar' => $this->avatar,
            'role' => $this->role->value,
            'is_blocked' => $this->is_blocked,
            'created_at' => $this->created_at?->toIso8601ZuluString(),
        ];
    }
}
