<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class WeatherSuggestionResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'weather' => [
                'condition' => $this->resource['weather']['condition'],
                'phrase' => $this->resource['weather']['phrase'],
                'temp' => $this->resource['weather']['temp'],
                'city' => $this->resource['weather']['city'],
            ],
            'genres' => $this->resource['genres'],
            'movies' => $this->resource['movies'],
        ];
    }
}
