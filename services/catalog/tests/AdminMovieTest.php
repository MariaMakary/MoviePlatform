<?php

namespace Tests;

use App\Http\Requests\UpdateMovieRequest;
use App\Models\Genre;
use App\Models\Movie;
use Firebase\JWT\JWT;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Lumen\Testing\DatabaseTransactions;
use MoviePlatform\Common\Integration\RabbitMqConnection;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;

class AdminMovieTest extends TestCase
{
    use DatabaseTransactions;

    private const ADMIN = '0193a7c0-1111-7aaa-8bbb-000000000001';

    private const USER = '0193a7c0-2222-7aaa-8bbb-000000000002';

    private const TMDB_ID = 900500001;

    private const UNKNOWN = '0193a7c1-9999-7aaa-8bbb-000000000999';

    private array $published = [];

    private bool $tmdbDown = false;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'jwt.public_key_path' => base_path('tests/Fixtures/jwt/test-public.pem'),
            'services.tmdb.key' => 'test-key',
        ]);

        $channel = $this->createMock(AMQPChannel::class);
        $channel->method('basic_publish')->willReturnCallback(function (AMQPMessage $message, string $exchange, string $routingKey) {
            $this->published[] = [$routingKey, json_decode($message->getBody(), true)];
        });
        $rabbitMq = $this->createMock(RabbitMqConnection::class);
        $rabbitMq->method('channel')->willReturn($channel);
        $rabbitMq->method('exchange')->willReturn('movieplatform.events');
        $this->app->instance(RabbitMqConnection::class, $rabbitMq);

        Http::fake(function (Request $request) {
            if ($this->tmdbDown) {
                return Http::response([], 503);
            }

            $path = parse_url($request->url(), PHP_URL_PATH);

            if (str_ends_with($path, '/movie/popular')) {
                return Http::response(['page' => 1, 'total_pages' => 1, 'results' => [['id' => self::TMDB_ID, 'title' => 'From TMDB']]]);
            }

            if (preg_match('#/movie/(\d+)$#', $path, $m)) {
                return (int) $m[1] === self::TMDB_ID
                    ? Http::response([
                        'id' => self::TMDB_ID,
                        'title' => 'From TMDB',
                        'overview' => 'TMDB overview.',
                        'poster_path' => '/tmdb.jpg',
                        'release_date' => '2010-07-15',
                        'runtime' => 148,
                        'vote_average' => 8.4,
                        'popularity' => 80,
                        'genres' => [['id' => 990001, 'name' => 'Test Action']],
                        'credits' => ['cast' => [], 'crew' => []],
                        'videos' => ['results' => []],
                    ])
                    : Http::response(['status_message' => 'Not found.'], 404);
            }

            return Http::response([], 500);
        });
    }

    private function auth(string $role = 'admin', string $userId = self::ADMIN): array
    {
        $this->app['auth']->forgetGuards();

        $token = JWT::encode(
            ['sub' => $userId, 'role' => $role, 'name' => 'Someone', 'exp' => time() + 1800],
            file_get_contents(base_path('tests/Fixtures/jwt/test-private.pem')),
            'RS256',
        );

        return ['Authorization' => "Bearer $token"];
    }

    private function edit(Movie $movie, array $overrides = []): void
    {
        $this->json('PUT', "/api/admin/movies/{$movie->id}", array_merge([
            'title' => 'Edited title',
            'overview' => 'Edited overview.',
            'poster_path' => '/edited.jpg',
            'release_date' => '2011-01-02',
            'runtime' => 120,
            'genre_ids' => [],
        ], $overrides), $this->auth());
    }

    public function test_a_normal_user_and_a_guest_get_refused_everywhere(): void
    {
        $movie = Movie::factory()->create();
        $calls = [
            ['GET', '/api/admin/movies', []],
            ['PUT', "/api/admin/movies/{$movie->id}", ['title' => 'x']],
            ['DELETE', "/api/admin/movies/{$movie->id}", []],
            ['POST', '/api/admin/movies/import', ['tmdb_id' => self::TMDB_ID]],
        ];

        foreach ($calls as [$method, $uri, $body]) {
            $this->json($method, $uri, $body, $this->auth('user', self::USER));
            $this->seeStatusCode(403);

            $this->app['auth']->forgetGuards();
            $this->json($method, $uri, $body);
            $this->seeStatusCode(401);
        }

        $this->seeInDatabase('movies', ['id' => $movie->id]);
        $this->assertSame([], $this->published);
    }

    public function test_the_list_is_paginated_searchable_and_shows_is_edited(): void
    {
        $edited = Movie::factory()->create(['title' => 'Zzadmin Alpha', 'is_edited' => true]);
        Movie::factory()->create(['title' => 'Zzadmin Beta']);
        Movie::factory()->create(['title' => 'Something else']);

        $this->json('GET', '/api/admin/movies?q=zzADMIN', [], $this->auth());

        $this->seeStatusCode(200);
        $body = json_decode($this->response->getContent(), true);
        $this->assertSame(['Zzadmin Alpha', 'Zzadmin Beta'], array_column($body['data'], 'title'));
        $this->assertSame([true, false], array_column($body['data'], 'is_edited'));
        $this->assertSame(['current_page' => 1, 'last_page' => 1, 'total' => 2], $body['meta']);
        $this->assertSame($edited->id, $body['data'][0]['id']);
        $this->assertArrayHasKey('tmdb_id', $body['data'][0]);
        $this->assertArrayHasKey('genre_ids', $body['data'][0]);

        $this->json('GET', '/api/admin/movies?q=100%25', [], $this->auth());
        $this->assertSame([], json_decode($this->response->getContent(), true)['data']);
    }

    public function test_an_edit_saves_the_fields_and_genres_and_sets_is_edited(): void
    {
        $movie = Movie::factory()->create();
        $genres = Genre::factory()->count(2)->create();

        $this->edit($movie, ['title' => '  Edited title  ', 'genre_ids' => $genres->pluck('id')->all()]);

        $this->seeStatusCode(200);
        $this->seeJson(['title' => 'Edited title', 'is_edited' => true, 'runtime' => 120, 'release_date' => '2011-01-02', 'poster_path' => '/edited.jpg']);
        $movie->refresh();
        $this->assertTrue($movie->is_edited);
        $this->assertSame('Edited overview.', $movie->overview);
        $this->assertEqualsCanonicalizing($genres->pluck('id')->all(), $movie->genres()->pluck('genres.id')->all());

        $this->edit($movie, ['overview' => null, 'poster_path' => null, 'release_date' => null, 'runtime' => null, 'genre_ids' => []]);
        $this->seeStatusCode(200);
        $movie->refresh();
        $this->assertNull($movie->overview);
        $this->assertNull($movie->runtime);
        $this->assertSame(0, $movie->genres()->count());
    }

    public function test_an_edit_survives_catalog_sync_popular(): void
    {
        $this->assertSame(0, $this->artisan('catalog:sync-popular', ['pages' => 1]));
        $movie = Movie::where('tmdb_id', self::TMDB_ID)->firstOrFail();
        $this->assertSame('From TMDB', $movie->title);

        $genre = Genre::factory()->create();
        $this->edit($movie, ['genre_ids' => [$genre->id]]);
        $this->seeStatusCode(200);

        $this->assertSame(0, $this->artisan('catalog:sync-popular', ['pages' => 1]));

        $movie->refresh();
        $this->assertSame('Edited title', $movie->title);
        $this->assertSame('Edited overview.', $movie->overview);
        $this->assertSame('/edited.jpg', $movie->poster_path);
        $this->assertSame(120, $movie->runtime);
        $this->assertTrue($movie->is_edited);
        $this->assertSame([$genre->id], $movie->genres()->pluck('genres.id')->all());
    }

    public function test_an_invalid_edit_gives_422(): void
    {
        $movie = Movie::factory()->create();
        $cases = [
            ['title' => ''],
            ['title' => str_repeat('a', 256)],
            ['poster_path' => 'https://evil.example/x.jpg'],
            ['release_date' => '15/07/2010'],
            ['runtime' => 0],
            ['genre_ids' => [self::UNKNOWN]],
            ['genre_ids' => ['not-a-uuid']],
            ['genre_ids' => 'x'],
        ];

        foreach ($cases as $overrides) {
            $this->edit($movie, $overrides);
            $this->seeStatusCode(422);
        }

        $genreIds = Genre::factory()->count(UpdateMovieRequest::MAX_GENRES + 1)->create()->pluck('id')->all();
        $this->edit($movie, ['genre_ids' => $genreIds]);
        $this->seeStatusCode(422);
        $this->assertSame(['genre_ids'], array_keys($this->response->getData(true)['errors']));

        $this->json('PUT', "/api/admin/movies/{$movie->id}", ['title' => 'Only a title'], $this->auth());
        $this->seeStatusCode(422);

        $this->assertFalse($movie->fresh()->is_edited);
    }

    public function test_editing_or_deleting_an_unknown_movie_gives_404(): void
    {
        $this->edit((new Movie())->forceFill(['id' => self::UNKNOWN]));
        $this->seeStatusCode(404);
        $this->seeJson(['message' => 'Movie not found.']);

        $this->json('DELETE', '/api/admin/movies/'.self::UNKNOWN, [], $this->auth());
        $this->seeStatusCode(404);
        $this->assertSame([], $this->published);
    }

    public function test_delete_removes_the_movie_and_publishes_movie_deleted(): void
    {
        $movie = Movie::factory()->create();
        $movie->genres()->attach(Genre::factory()->create()->id);

        $this->json('DELETE', "/api/admin/movies/{$movie->id}", [], $this->auth());

        $this->seeStatusCode(204);
        $this->assertSame('', $this->response->getContent());
        $this->notSeeInDatabase('movies', ['id' => $movie->id]);
        $this->notSeeInDatabase('movie_genre', ['movie_id' => $movie->id]);

        $this->assertCount(1, $this->published);
        [$routingKey, $envelope] = $this->published[0];
        $this->assertSame('movie.deleted', $routingKey);
        $this->assertSame('MovieDeleted', $envelope['event']);
        $this->assertSame(['movie_id' => $movie->id], $envelope['data']);
    }

    public function test_import_saves_a_tmdb_movie(): void
    {
        $this->json('POST', '/api/admin/movies/import', ['tmdb_id' => self::TMDB_ID], $this->auth());

        $this->seeStatusCode(201);
        $this->seeJson(['tmdb_id' => self::TMDB_ID, 'title' => 'From TMDB', 'is_edited' => false, 'genres' => ['Test Action']]);
        $this->seeInDatabase('movies', ['tmdb_id' => self::TMDB_ID, 'runtime' => 148]);
    }

    public function test_importing_an_already_imported_movie_gives_422(): void
    {
        Movie::factory()->create(['tmdb_id' => self::TMDB_ID]);

        $this->json('POST', '/api/admin/movies/import', ['tmdb_id' => self::TMDB_ID], $this->auth());

        $this->seeStatusCode(422);
        $this->seeJson(['tmdb_id' => ['This movie is already in the catalog.']]);
        Http::assertNothingSent();
    }

    public function test_import_errors(): void
    {
        $this->json('POST', '/api/admin/movies/import', ['tmdb_id' => 900500999], $this->auth());
        $this->seeStatusCode(422);
        $this->seeJson(['tmdb_id' => ['TMDB has no movie with this id.']]);

        foreach ([[], ['tmdb_id' => 'abc'], ['tmdb_id' => 0]] as $body) {
            $this->json('POST', '/api/admin/movies/import', $body, $this->auth());
            $this->seeStatusCode(422);
        }

        $this->tmdbDown = true;
        $this->json('POST', '/api/admin/movies/import', ['tmdb_id' => self::TMDB_ID], $this->auth());
        $this->seeStatusCode(503);
        $this->seeJson(['message' => 'TMDB is unavailable.']);
        $this->notSeeInDatabase('movies', ['tmdb_id' => self::TMDB_ID]);
    }
}
