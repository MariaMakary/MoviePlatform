<?php

namespace Tests;

use App\Enums\WeatherCode;
use App\Enums\WeatherCondition;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class WeatherSuggestionTest extends TestCase
{
    private const TOKEN = 'test-service-token';

    private int $weatherCode = 0;

    private bool $weatherDown = false;

    private bool $catalogDown = false;

    private array $requestedGenres = [];

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config(['internal.service_token' => self::TOKEN, 'internal.services.catalog' => 'http://catalog.test']);

        Http::fake(function (Request $request) {
            $url = $request->url();

            if (str_contains($url, 'nominatim')) {
                return Http::response(['address' => ['city' => 'Beirut']]);
            }

            if (str_contains($url, 'open-meteo')) {
                return $this->weatherDown
                    ? Http::response(['error' => true, 'reason' => 'down'], 503)
                    : Http::response(['current' => ['temperature_2m' => 18.5, 'weather_code' => $this->weatherCode]]);
            }

            if ($this->catalogDown) {
                throw new ConnectionException('Connection refused');
            }

            parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
            $this->requestedGenres = explode(',', $query['genres']);

            return Http::response(['data' => array_map(fn (string $genre) => [
                'id' => md5($genre),
                'title' => "A $genre movie",
                'poster_url' => null,
                'year' => 2020,
                'release_date' => '2020-01-01',
                'vote_average' => 7.5,
                'genres' => [$genre],
            ], $this->requestedGenres)]);
        });
    }

    private function body(): array
    {
        return json_decode($this->response->getContent(), true);
    }

    public function test_it_returns_the_weather_the_genres_and_the_movies(): void
    {
        $this->weatherCode = 61;

        $this->get('/api/suggestions/weather?lat=33.8938&lon=35.5018')->seeStatusCode(200);

        $this->assertSame([
            'data' => [
                'weather' => ['condition' => 'rain', 'phrase' => 'Light rain', 'temp' => 18.5, 'city' => 'Beirut'],
                'genres' => ['Drama', 'Romance'],
                'movies' => [
                    ['id' => md5('Drama'), 'title' => 'A Drama movie', 'poster_url' => null, 'year' => 2020, 'release_date' => '2020-01-01', 'vote_average' => 7.5, 'genres' => ['Drama']],
                    ['id' => md5('Romance'), 'title' => 'A Romance movie', 'poster_url' => null, 'year' => 2020, 'release_date' => '2020-01-01', 'vote_average' => 7.5, 'genres' => ['Romance']],
                ],
            ],
        ], $this->body());
    }

    public function test_it_asks_catalog_discover_for_10_movies_with_the_service_token(): void
    {
        $this->get('/api/suggestions/weather?lat=33.89&lon=35.5')->seeStatusCode(200);

        Http::assertSent(fn (Request $request) => str_starts_with($request->url(), 'http://catalog.test/api/internal/discover?')
            && str_contains($request->url(), 'genres=Comedy%2CAdventure')
            && str_contains($request->url(), 'limit=10')
            && $request->hasHeader('X-Service-Token', self::TOKEN));
    }

    public function test_different_weather_conditions_give_different_genres_and_movies(): void
    {
        $seen = [];

        foreach ([0 => 'clear', 3 => 'clouds', 63 => 'rain', 95 => 'storm', 73 => 'snow'] as $code => $condition) {
            Cache::flush();
            $this->weatherCode = $code;

            $this->get('/api/suggestions/weather?lat=33.89&lon=35.5')->seeStatusCode(200);

            $data = $this->body()['data'];
            $this->assertSame($condition, $data['weather']['condition']);
            $this->assertSame(WeatherCondition::from($condition)->genres(), $data['genres']);
            $seen[$condition] = array_column($data['movies'], 'title');
        }

        $this->assertCount(5, array_unique(array_map('serialize', $seen)));
    }

    public function test_every_weather_code_maps_to_the_right_condition(): void
    {
        $mapped = [];

        foreach (WeatherCode::cases() as $code) {
            $mapped[WeatherCondition::fromCode($code->value)->value][] = $code->value;
        }

        ksort($mapped);
        $this->assertSame([
            'clear' => [0, 1, 2],
            'clouds' => [3, 45, 48],
            'rain' => [51, 53, 55, 56, 57, 61, 63, 65, 66, 67, 80, 81, 82],
            'snow' => [71, 73, 75, 77, 85, 86],
            'storm' => [95, 96, 99],
        ], $mapped);
    }

    public function test_an_unknown_code_counts_as_clouds(): void
    {
        $this->assertSame(WeatherCondition::Clouds, WeatherCondition::fromCode(42));
        $this->assertSame(WeatherCondition::Clouds, WeatherCondition::fromCode(-1));
    }

    public function test_weather_down_gives_503(): void
    {
        $this->weatherDown = true;

        $this->get('/api/suggestions/weather?lat=33.89&lon=35.5')->seeStatusCode(503)->seeJsonEquals(['message' => 'Weather is unavailable.']);
    }

    public function test_catalog_down_gives_503(): void
    {
        $this->catalogDown = true;

        $this->get('/api/suggestions/weather?lat=33.89&lon=35.5')->seeStatusCode(503)->seeJsonEquals(['message' => 'Catalog is unavailable.']);
    }

    public function test_invalid_coordinates_are_refused(): void
    {
        foreach (['', 'lat=33.8', 'lat=91&lon=35', 'lat=33&lon=181', 'lat=abc&lon=35'] as $query) {
            $this->get('/api/suggestions/weather?'.$query)->seeStatusCode(422);
        }

        Http::assertNothingSent();
    }

    public function test_no_token_is_needed(): void
    {
        $this->get('/api/suggestions/weather?lat=33.89&lon=35.5')->seeStatusCode(200);
    }
}
