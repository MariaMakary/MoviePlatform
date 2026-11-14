<?php

namespace Tests;

use App\Enums\WeatherCondition;
use App\Integration\Clients\WeatherClient;
use App\Integration\Clients\WeatherUnavailable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class WeatherClientTest extends TestCase
{
    private int $weatherStatus = 200;

    private int $cityStatus = 200;

    private bool $timeout = false;

    private array $weather = [];

    private array $city = [];

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        $this->weather = ['latitude' => 33.9, 'longitude' => 35.5, 'current' => ['time' => '2026-11-12T11:00', 'interval' => 900, 'temperature_2m' => 22.4, 'weather_code' => 1]];
        $this->city = ['place_id' => 1, 'address' => ['city' => 'Beirut', 'country' => 'Lebanon']];

        Http::fake(function (Request $request) {
            if ($this->timeout) {
                throw new ConnectionException('cURL error 28: Operation timed out');
            }

            return str_contains($request->url(), 'nominatim.openstreetmap.org')
                ? Http::response($this->cityStatus === 200 ? $this->city : ['error' => 'Too many requests'], $this->cityStatus)
                : Http::response($this->weatherStatus === 200 ? $this->weather : ['error' => true, 'reason' => 'Weather failed'], $this->weatherStatus);
        });
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function client(): WeatherClient
    {
        return app(WeatherClient::class);
    }

    private function sent(string $host): int
    {
        return count(Http::recorded(fn (Request $request) => str_contains($request->url(), $host)));
    }

    private function query(Request $request): array
    {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return $query;
    }

    public function test_it_returns_the_weather_and_the_city(): void
    {
        $this->assertSame(
            ['icon_code' => 1, 'phrase' => 'Mainly clear', 'temp' => 22.4, 'city' => 'Beirut'],
            $this->client()->current(33.8938, 35.5018),
        );
    }

    public function test_the_requests_match_open_meteo_and_nominatim(): void
    {
        $this->client()->current(33.8938, 35.5018);

        Http::assertSent(fn (Request $request) => str_starts_with($request->url(), 'https://api.open-meteo.com/v1/forecast?')
            && $this->query($request) === ['latitude' => '33.89', 'longitude' => '35.5', 'current' => 'temperature_2m,weather_code']);

        Http::assertSent(fn (Request $request) => str_starts_with($request->url(), 'https://nominatim.openstreetmap.org/reverse?')
            && $this->query($request) === ['format' => 'jsonv2', 'lat' => '33.89', 'lon' => '35.5', 'zoom' => '10', 'accept-language' => 'en']
            && str_starts_with($request->header('User-Agent')[0] ?? '', 'KAIA-MoviePlatform/'));
    }

    public function test_two_calls_for_nearby_coordinates_within_30_minutes_call_the_apis_once(): void
    {
        $this->client()->current(33.8938, 35.5018);
        Carbon::setTestNow(Carbon::now()->addMinutes(29));
        $this->client()->current(33.8941, 35.4996);

        $this->assertSame(1, $this->sent('api.open-meteo.com'));
        $this->assertSame(1, $this->sent('nominatim.openstreetmap.org'));
    }

    public function test_coordinates_in_another_cell_call_the_apis_again(): void
    {
        $this->client()->current(33.89, 35.50);
        $this->client()->current(33.90, 35.50);

        $this->assertSame(2, $this->sent('api.open-meteo.com'));
    }

    public function test_after_30_minutes_the_weather_is_fetched_again_but_the_city_is_kept_for_7_days(): void
    {
        $this->client()->current(33.89, 35.50);

        Carbon::setTestNow(Carbon::now()->addMinutes(31));
        $this->client()->current(33.89, 35.50);
        $this->assertSame(2, $this->sent('api.open-meteo.com'));
        $this->assertSame(1, $this->sent('nominatim.openstreetmap.org'));

        Carbon::setTestNow(Carbon::now()->addDays(7));
        $this->client()->current(33.89, 35.50);
        $this->assertSame(2, $this->sent('nominatim.openstreetmap.org'));
    }

    public function test_a_town_or_village_is_used_when_there_is_no_city(): void
    {
        $this->city = ['address' => ['village' => 'Faraya', 'country' => 'Lebanon']];

        $this->assertSame('Faraya', $this->client()->current(34.00, 35.83)['city']);
    }

    public function test_a_place_without_a_name_has_no_city_and_is_not_asked_again(): void
    {
        $this->city = ['error' => 'Unable to geocode'];

        $this->assertNull($this->client()->current(34.20, 35.10)['city']);

        Carbon::setTestNow(Carbon::now()->addMinutes(31));
        $this->client()->current(34.20, 35.10);
        $this->assertSame(1, $this->sent('nominatim.openstreetmap.org'));
    }

    public function test_every_weather_code_has_a_phrase(): void
    {
        foreach (WeatherCondition::cases() as $condition) {
            $this->assertNotSame('', $condition->phrase());
        }

        $this->weather['current']['weather_code'] = 95;
        $this->assertSame(['icon_code' => 95, 'phrase' => 'Thunderstorm'], array_intersect_key($this->client()->current(33.89, 35.50), ['icon_code' => 0, 'phrase' => 0]));
    }

    public function test_errors_and_rate_limits_throw_weather_unavailable_with_503(): void
    {
        foreach ([400, 401, 403, 429, 500] as $status) {
            Cache::flush();
            $this->weatherStatus = $status;

            try {
                $this->client()->current(33.89, 35.50);
                $this->fail("Status $status should throw.");
            } catch (WeatherUnavailable $e) {
                $this->assertSame(503, $e->getStatusCode());
                $this->assertSame('Weather is unavailable.', $e->getMessage());
            }
        }

        $this->weatherStatus = 200;
        $this->cityStatus = 429;
        Cache::flush();
        $this->expectException(WeatherUnavailable::class);
        $this->client()->current(33.89, 35.50);
    }

    public function test_a_timeout_throws_weather_unavailable(): void
    {
        $this->timeout = true;

        $this->expectException(WeatherUnavailable::class);
        $this->client()->current(33.89, 35.50);
    }

    public function test_an_unexpected_answer_throws_weather_unavailable(): void
    {
        foreach ([['current' => []], ['current' => ['temperature_2m' => 20, 'weather_code' => 42]]] as $answer) {
            Cache::flush();
            $this->weather = $answer;

            try {
                $this->client()->current(33.89, 35.50);
                $this->fail('An unexpected answer should throw.');
            } catch (WeatherUnavailable) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_a_failure_is_not_cached(): void
    {
        $this->weatherStatus = 500;

        try {
            $this->client()->current(33.89, 35.50);
        } catch (WeatherUnavailable) {
        }

        $this->weatherStatus = 200;
        $this->assertSame('Mainly clear', $this->client()->current(33.89, 35.50)['phrase']);
        $this->assertSame(2, $this->sent('api.open-meteo.com'));
    }
}
