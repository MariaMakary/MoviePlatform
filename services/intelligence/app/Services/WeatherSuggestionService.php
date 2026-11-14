<?php

namespace App\Services;

use App\Enums\WeatherCondition;
use App\Integration\Clients\CatalogClient;
use App\Integration\Clients\WeatherClient;

class WeatherSuggestionService
{
    public const MOVIES = 10;

    public function __construct(private WeatherClient $weather, private CatalogClient $catalog) {}

    public function suggest(float $lat, float $lon): array
    {
        $weather = $this->weather->current($lat, $lon);
        $condition = WeatherCondition::fromCode($weather['icon_code']);
        $genres = $condition->genres();

        return [
            'weather' => [
                'condition' => $condition->value,
                'phrase' => $weather['phrase'],
                'temp' => $weather['temp'],
                'city' => $weather['city'],
            ],
            'genres' => $genres,
            'movies' => $this->catalog->discover($genres, self::MOVIES),
        ];
    }
}
