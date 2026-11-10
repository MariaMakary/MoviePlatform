<?php

namespace Tests;

use App\Enums\CreditRole;
use App\Integration\Clients\TmdbClient;
use App\Integration\Clients\TmdbUnavailable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TmdbClientTest extends TestCase
{
    private const KEY = 'test-secret-key-123';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.tmdb.key' => self::KEY, 'services.tmdb.url' => 'https://api.themoviedb.org/3']);
    }

    private function client(): TmdbClient
    {
        return app(TmdbClient::class);
    }

    private function tmdbMovie(array $overrides = []): array
    {
        return array_merge([
            'id' => 27205,
            'title' => 'Inception',
            'overview' => 'A thief who steals corporate secrets…',
            'poster_path' => '/poster.jpg',
            'backdrop_path' => '/backdrop.jpg',
            'release_date' => '2010-07-15',
            'vote_average' => 8.369,
            'popularity' => 83.95,
            'genre_ids' => [28, 878],
        ], $overrides);
    }

    public function test_popular_returns_movies_ready_to_save(): void
    {
        Http::fake(['api.themoviedb.org/*' => Http::response([
            'page' => 2,
            'total_pages' => 500,
            'total_results' => 10000,
            'results' => [$this->tmdbMovie(), $this->tmdbMovie(['id' => 1, 'title' => 'No date', 'release_date' => '', 'overview' => '', 'poster_path' => null])],
        ])]);

        $result = $this->client()->popular(2);

        $this->assertSame(2, $result['page']);
        $this->assertSame(500, $result['total_pages']);
        $this->assertSame(10000, $result['total_results']);
        $this->assertSame([
            'movie' => [
                'tmdb_id' => 27205,
                'title' => 'Inception',
                'overview' => 'A thief who steals corporate secrets…',
                'poster_path' => '/poster.jpg',
                'backdrop_path' => '/backdrop.jpg',
                'release_date' => '2010-07-15',
                'runtime' => null,
                'vote_average' => 8.4,
                'popularity' => 83.95,
            ],
            'genre_tmdb_ids' => [28, 878],
        ], $result['results'][0]);
        $this->assertNull($result['results'][1]['movie']['release_date']);
        $this->assertNull($result['results'][1]['movie']['overview']);

        Http::assertSent(function (Request $request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return str_starts_with($request->url(), 'https://api.themoviedb.org/3/movie/popular?')
                && $query === ['language' => 'en-US', 'api_key' => self::KEY, 'page' => '2'];
        });
    }

    public function test_a_read_access_token_is_sent_as_a_bearer_header_not_in_the_url(): void
    {
        config(['services.tmdb.key' => 'eyJhbGciOiJIUzI1NiJ9.token']);
        Http::fake(['*' => Http::response(['genres' => []])]);

        $this->client()->genres();

        Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer eyJhbGciOiJIUzI1NiJ9.token')
            && ! str_contains($request->url(), 'api_key'));
    }

    public function test_upcoming_search_and_discover_call_the_right_endpoints(): void
    {
        Http::fake(['*' => Http::response(['page' => 1, 'total_pages' => 1, 'results' => []])]);

        $this->client()->upcoming();
        $this->client()->search('inception');
        $this->client()->discover(878, 2010);
        $this->client()->discover();

        $sent = collect(Http::recorded())->map(fn ($pair) => $pair[0]);
        $this->assertStringContainsString('/movie/upcoming?', $sent[0]->url());
        $this->assertStringContainsString('region=US', $sent[0]->url());
        $this->assertSame('inception', $sent[1]['query']);
        $this->assertStringContainsString('/search/movie?', $sent[1]->url());
        $this->assertSame('878', (string) $sent[2]['with_genres']);
        $this->assertSame('2010', (string) $sent[2]['primary_release_year']);
        $this->assertSame('popularity.desc', $sent[2]['sort_by']);
        $this->assertArrayNotHasKey('with_genres', $sent[3]->data());
    }

    public function test_movie_details_include_genres_cast_directors_and_trailer(): void
    {
        $cast = array_map(fn (int $i) => ['id' => 100 + $i, 'name' => "Actor $i", 'character' => "Role $i", 'order' => 24 - $i, 'profile_path' => null, 'known_for_department' => 'Acting'], range(0, 24));

        Http::fake(['*' => Http::response($this->tmdbMovie([
            'runtime' => 148,
            'genres' => [['id' => 28, 'name' => 'Action'], ['id' => 878, 'name' => 'Science Fiction']],
            'credits' => [
                'cast' => $cast,
                'crew' => [
                    ['id' => 525, 'name' => 'Christopher Nolan', 'job' => 'Director', 'profile_path' => '/nolan.jpg', 'known_for_department' => 'Directing'],
                    ['id' => 525, 'name' => 'Christopher Nolan', 'job' => 'Director', 'profile_path' => '/nolan.jpg', 'known_for_department' => 'Directing'],
                    ['id' => 947, 'name' => 'Hans Zimmer', 'job' => 'Original Music Composer', 'known_for_department' => 'Sound'],
                ],
            ],
            'videos' => ['results' => [
                ['site' => 'YouTube', 'type' => 'Teaser', 'key' => 'teaser1', 'official' => true],
                ['site' => 'YouTube', 'type' => 'Trailer', 'key' => 'fan', 'official' => false],
                ['site' => 'YouTube', 'type' => 'Trailer', 'key' => 'YoHD9XEInc0', 'official' => true],
                ['site' => 'Vimeo', 'type' => 'Trailer', 'key' => 'vimeo1', 'official' => true],
            ]],
        ]))]);

        $result = $this->client()->movie(27205);

        $this->assertSame(148, $result['movie']['runtime']);
        $this->assertSame('YoHD9XEInc0', $result['movie']['trailer_key']);
        $this->assertSame([['tmdb_id' => 28, 'name' => 'Action'], ['tmdb_id' => 878, 'name' => 'Science Fiction']], $result['genres']);

        $this->assertCount(TmdbClient::CAST_LIMIT, $result['cast']);
        $this->assertSame(0, $result['cast'][0]['cast_order']);
        $this->assertSame(['tmdb_id' => 124, 'name' => 'Actor 24', 'profile_path' => null, 'known_for' => 'Acting'], $result['cast'][0]['person']);
        $this->assertSame('Role 24', $result['cast'][0]['character']);

        $this->assertSame([['person' => ['tmdb_id' => 525, 'name' => 'Christopher Nolan', 'profile_path' => '/nolan.jpg', 'known_for' => 'Directing']]], $result['directors']);

        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/movie/27205?') && $request['append_to_response'] === 'credits,videos');
    }

    public function test_a_movie_without_a_trailer_has_a_null_trailer_key(): void
    {
        Http::fake(['*' => Http::response($this->tmdbMovie(['videos' => ['results' => []], 'credits' => []]))]);

        $this->assertNull($this->client()->movie(27205)['movie']['trailer_key']);
    }

    public function test_a_movie_or_person_missing_on_tmdb_returns_null(): void
    {
        Http::fake(['*' => Http::response(['status_message' => 'The resource you requested could not be found.'], 404)]);

        $this->assertNull($this->client()->movie(999999999));
        $this->assertNull($this->client()->person(999999999));
    }

    public function test_person_with_movie_credits_only(): void
    {
        Http::fake(['*' => Http::response([
            'id' => 525,
            'name' => 'Christopher Nolan',
            'biography' => 'British-American filmmaker.',
            'birthday' => '1970-07-30',
            'profile_path' => '/nolan.jpg',
            'known_for_department' => 'Directing',
            'combined_credits' => [
                'cast' => [
                    $this->tmdbMovie(['id' => 1, 'title' => 'Cameo', 'media_type' => 'movie', 'character' => 'Himself']),
                    ['id' => 2, 'name' => 'A TV show', 'media_type' => 'tv', 'character' => 'Host'],
                ],
                'crew' => [
                    $this->tmdbMovie(['media_type' => 'movie', 'job' => 'Director']),
                    $this->tmdbMovie(['media_type' => 'movie', 'job' => 'Writer']),
                ],
            ],
        ])]);

        $result = $this->client()->person(525);

        $this->assertSame([
            'tmdb_id' => 525,
            'name' => 'Christopher Nolan',
            'profile_path' => '/nolan.jpg',
            'known_for' => 'Directing',
            'biography' => 'British-American filmmaker.',
            'birthday' => '1970-07-30',
        ], $result['person']);

        $this->assertCount(2, $result['movies']);
        $this->assertSame([1, CreditRole::Cast, 'Himself'], [$result['movies'][0]['movie']['tmdb_id'], $result['movies'][0]['role'], $result['movies'][0]['character']]);
        $this->assertSame([27205, CreditRole::Director, null], [$result['movies'][1]['movie']['tmdb_id'], $result['movies'][1]['role'], $result['movies'][1]['character']]);
        $this->assertSame([28, 878], $result['movies'][1]['genre_tmdb_ids']);
    }

    public function test_genre_list(): void
    {
        Http::fake(['*' => Http::response(['genres' => [['id' => 28, 'name' => 'Action'], ['id' => 18, 'name' => 'Drama']]])]);

        $this->assertSame([['tmdb_id' => 28, 'name' => 'Action'], ['tmdb_id' => 18, 'name' => 'Drama']], $this->client()->genres());

        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/genre/movie/list?'));
    }

    public function test_a_tmdb_error_throws_tmdb_unavailable(): void
    {
        Http::fake(['*' => Http::response(['status_message' => 'Internal error.'], 500)]);

        $this->expectException(TmdbUnavailable::class);
        $this->expectExceptionMessage('TMDB is unavailable.');

        $this->client()->popular();
    }

    public function test_an_invalid_key_throws_tmdb_unavailable(): void
    {
        Http::fake(['*' => Http::response(['status_message' => 'Invalid API key: You must be granted a valid key.'], 401)]);

        $this->expectException(TmdbUnavailable::class);

        $this->client()->genres();
    }

    public function test_a_body_that_is_not_json_throws_tmdb_unavailable(): void
    {
        Http::fake(['*' => Http::response('<html>Bad gateway</html>', 200)]);

        $this->expectException(TmdbUnavailable::class);

        $this->client()->popular();
    }

    public function test_an_endpoint_turns_a_tmdb_failure_into_503_json_without_the_key(): void
    {
        Http::fake(['*' => Http::response(['status_message' => 'Service unavailable'], 503)]);
        $this->app->router->get('/api/test-tmdb', fn () => response()->json(app(TmdbClient::class)->popular()));

        $this->get('/api/test-tmdb')
            ->seeStatusCode(503)
            ->seeJsonEquals(['message' => 'TMDB is unavailable.']);

        $this->assertStringNotContainsString(self::KEY, $this->response->getContent());
    }

    public function test_a_timeout_is_logged_without_the_key(): void
    {
        Log::spy();
        Http::fake(function (Request $request) {
            throw new ConnectionException('cURL error 28: Operation timed out after 5001 milliseconds for '.$request->url());
        });

        try {
            $this->client()->popular();
            $this->fail('TmdbUnavailable was not thrown.');
        } catch (TmdbUnavailable $e) {
            $this->assertStringNotContainsString(self::KEY, $e->getMessage());
            $this->assertNull($e->getPrevious(), 'The original exception (its message holds the URL and key) must not be attached.');
        }

        Log::shouldHaveReceived('warning')->once()->withArgs(function (string $message, array $context) {
            $logged = $message.json_encode($context, JSON_UNESCAPED_SLASHES);

            return str_contains($logged, '/movie/popular')
                && str_contains($logged, 'api_key=***')
                && ! str_contains($logged, self::KEY);
        });
    }

    public function test_the_request_has_a_5_second_timeout(): void
    {
        $this->assertSame(5, config('services.tmdb.timeout'));
        $this->assertSame('en-US', config('services.tmdb.language'));
    }
}
