<?php

namespace App\Http\Resources;

use App\Enums\CreditRole;
use App\Http\Resources\Concerns\TmdbImageUrl;
use Illuminate\Http\Resources\Json\JsonResource;

class PersonCreditResource extends JsonResource
{
    use TmdbImageUrl;

    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            $this->mergeWhen($this->pivot?->role === CreditRole::Cast, fn () => ['character' => $this->pivot->character]),
            'photo_url' => $this->imageUrl($this->profile_path, 'profile'),
        ];
    }
}
