<?php

namespace App\Integration\Clients;

use App\Enums\WeatherCode;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WeatherClient
{
    public function current(float $lat, float $lon): array
    {
        [$lat, $lon] = [round($lat, 2), round($lon, 2)];

        return Cache::remember(
            "weather:current:{$lat},{$lon}",
            Carbon::now()->addMinutes(config('services.weather.weather_minutes')),
            fn () => $this->fetchCurrent($lat, $lon),
        );
    }

    private function fetchCurrent(float $lat, float $lon): array
    {
        $city = $this->city($lat, $lon);

        $current = $this->get('Open-Meteo', config('services.weather.open_meteo_url').'/forecast', [
            'latitude' => $lat,
            'longitude' => $lon,
            'current' => 'temperature_2m,weather_code',
        ])->json('current');

        $condition = is_numeric($current['weather_code'] ?? null) ? WeatherCode::tryFrom((int) $current['weather_code']) : null;

        if ($condition === null || ! is_numeric($current['temperature_2m'] ?? null)) {
            throw $this->unavailable('Open-Meteo', null, 'unexpected answer');
        }

        return [
            'icon_code' => $condition->value,
            'phrase' => $condition->phrase(),
            'temp' => (float) $current['temperature_2m'],
            'city' => $city,
        ];
    }

    private function city(float $lat, float $lon): ?string
    {
        return Cache::remember(
            "weather:city:{$lat},{$lon}",
            Carbon::now()->addDays(config('services.weather.city_days')),
            fn () => ['name' => $this->fetchCity($lat, $lon)],
        )['name'];
    }

    private function fetchCity(float $lat, float $lon): ?string
    {
        $address = $this->get('Nominatim', config('services.weather.nominatim_url').'/reverse', [
            'format' => 'jsonv2',
            'lat' => $lat,
            'lon' => $lon,
            'zoom' => 10,
            'accept-language' => 'en',
        ])->json('address');

        foreach (['city', 'town', 'village', 'municipality'] as $field) {
            $name = is_array($address) ? ($address[$field] ?? null) : null;

            if (is_string($name) && trim($name) !== '') {
                return trim($name);
            }
        }

        return null;
    }

    private function get(string $api, string $url, array $query): Response
    {
        try {
            $response = Http::withUserAgent(config('services.weather.user_agent'))
                ->acceptJson()
                ->timeout(config('services.weather.timeout'))
                ->get($url, $query);
        } catch (ConnectionException $e) {
            throw $this->unavailable($api, null, $e->getMessage());
        }

        if (! $response->successful()) {
            throw $this->unavailable($api, $response, $response->json('reason') ?? $response->json('error'));
        }

        return $response;
    }

    private function unavailable(string $api, ?Response $response, mixed $error): WeatherUnavailable
    {
        Log::warning("{$api} weather request failed", [
            'status' => $response?->status(),
            'error' => is_string($error) ? $error : null,
        ]);

        return new WeatherUnavailable();
    }
}
