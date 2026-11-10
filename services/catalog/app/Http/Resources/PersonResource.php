<?php

namespace App\Http\Resources;

use App\Enums\CreditRole;
use App\Http\Resources\Concerns\TmdbImageUrl;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/** A person's page. Expects `movies` loaded with their genres. */
class PersonResource extends JsonResource
{
    use TmdbImageUrl;

    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'photo_url' => $this->imageUrl($this->profile_path, 'profile'),
            'biography' => $this->biography,
            'birthday' => $this->birthday?->toDateString(),
            'known_for' => $this->known_for,
            'filmography' => [
                'acting' => $this->credits(CreditRole::Cast),
                'directing' => $this->credits(CreditRole::Director),
            ],
        ];
    }

    // Newest first; movies without a release date last.
    private function credits(CreditRole $role): Collection
    {
        return MovieCardResource::collection(
            $this->movies
                ->filter(fn ($movie) => $movie->pivot->role === $role)
                ->sortBy([
                    fn ($a, $b) => ($b->release_date?->toDateString() ?? '') <=> ($a->release_date?->toDateString() ?? ''),
                    fn ($a, $b) => $a->title <=> $b->title,
                ])
                ->values()
        )->collection;
    }
}
