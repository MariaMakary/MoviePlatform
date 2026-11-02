<?php

namespace Tests;

use App\Models\MovieList;
use Firebase\JWT\JWT;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Lumen\Testing\DatabaseTransactions;

class ListMovieApiTest extends TestCase
{
    use DatabaseTransactions;

    private const ME = '0193a7c0-1111-7aaa-8bbb-000000000001';

    private const OTHER = '0193a7c0-2222-7aaa-8bbb-000000000002';

    private const MOVIE = '0193a7c1-3333-7aaa-8bbb-000000000100';

    private const OTHER_MOVIE = '0193a7c1-3333-7aaa-8bbb-000000000200';

    private const THIRD_MOVIE = '0193a7c1-3333-7aaa-8bbb-000000000300';

    protected function setUp(): void
    {
        parent::setUp();

        config(['jwt.public_key_path' => base_path('tests/Fixtures/jwt/test-public.pem')]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function auth(string $userId = self::ME, string $role = 'user'): array
    {
        $token = JWT::encode(
            ['sub' => $userId, 'role' => $role, 'name' => 'Joy Alahel', 'exp' => time() + 1800],
            file_get_contents(base_path('tests/Fixtures/jwt/test-private.pem')),
            'RS256',
        );

        return ['Authorization' => "Bearer $token"];
    }

    // The jwt guard remembers its user for the life of the app; a real request gets a new one.
    private function newRequest(): static
    {
        $this->app['auth']->forgetGuards();

        return $this;
    }

    private function list(array $attributes = []): MovieList
    {
        return MovieList::forceCreate(array_merge([
            'user_id' => self::ME,
            'title' => 'Best of Nolan',
            'created_at' => Carbon::parse('2026-11-01T10:00:00Z'),
            'updated_at' => Carbon::parse('2026-11-01T10:00:00Z'),
        ], $attributes));
    }

    private function addMovie(MovieList $list, string $movieId = self::MOVIE, string $at = '2026-11-01T10:00:00Z'): void
    {
        DB::table('list_movie')->insert(['list_id' => $list->id, 'movie_id' => $movieId, 'created_at' => Carbon::parse($at), 'updated_at' => Carbon::parse($at)]);
    }

    private function movieCount(MovieList $list): int
    {
        return DB::table('list_movie')->where('list_id', $list->id)->count();
    }

    private function body(): array
    {
        return json_decode($this->response->getContent(), true);
    }

    public function test_every_route_needs_a_token(): void
    {
        $id = (string) Str::uuid();

        $this->json('POST', "/api/lists/$id/movies", ['movie_id' => self::MOVIE])->seeStatusCode(401)->seeJsonEquals(['message' => 'Unauthenticated.']);
        $this->json('DELETE', "/api/lists/$id/movies/".self::MOVIE)->seeStatusCode(401)->seeJsonEquals(['message' => 'Unauthenticated.']);
    }

    public function test_the_owner_adds_a_movie_and_the_list_is_updated(): void
    {
        $list = $this->list();
        Carbon::setTestNow('2026-11-03T18:20:00Z');

        $this->json('POST', "/api/lists/{$list->id}/movies", ['movie_id' => self::MOVIE], $this->auth())
            ->seeStatusCode(201)
            ->seeJsonEquals(['data' => ['list_id' => $list->id, 'movie_id' => self::MOVIE, 'created_at' => '2026-11-03T18:20:00Z']]);

        $this->assertSame(1, $this->movieCount($list));
        $this->assertSame('2026-11-03T18:20:00Z', $list->fresh()->updated_at->toIso8601ZuluString());
    }

    public function test_the_same_movie_twice_is_refused_and_does_not_update_the_list(): void
    {
        $list = $this->list();
        $this->addMovie($list);
        Carbon::setTestNow('2026-11-03T18:20:00Z');

        $this->json('POST', "/api/lists/{$list->id}/movies", ['movie_id' => self::MOVIE], $this->auth())->seeStatusCode(422);

        $this->assertSame(['Already in this list'], $this->body()['errors']['movie_id']);
        $this->assertSame(1, $this->movieCount($list));
        $this->assertSame('2026-11-01T10:00:00Z', $list->fresh()->updated_at->toIso8601ZuluString());
    }

    public function test_the_same_movie_can_be_in_two_lists(): void
    {
        $this->addMovie($this->list());
        $second = $this->list(['title' => 'Second']);

        $this->json('POST', "/api/lists/{$second->id}/movies", ['movie_id' => self::MOVIE], $this->auth())->seeStatusCode(201);
    }

    public function test_an_invalid_movie_id_is_refused(): void
    {
        $list = $this->list();

        foreach ([[], ['movie_id' => 'abc']] as $payload) {
            $this->json('POST', "/api/lists/{$list->id}/movies", $payload, $this->auth())
                ->seeStatusCode(422)
                ->seeJsonStructure(['message', 'errors' => ['movie_id']]);
        }

        $this->json('DELETE', "/api/lists/{$list->id}/movies/abc", [], $this->auth())->seeStatusCode(422);
    }

    public function test_the_owner_removes_a_movie_and_the_list_is_updated(): void
    {
        $list = $this->list();
        $this->addMovie($list);
        $this->addMovie($list, self::OTHER_MOVIE);
        Carbon::setTestNow('2026-11-03T18:20:00Z');

        $this->json('DELETE', "/api/lists/{$list->id}/movies/".self::MOVIE, [], $this->auth())->seeStatusCode(204);

        $this->assertSame('', $this->response->getContent());
        $this->assertSame([self::OTHER_MOVIE], DB::table('list_movie')->where('list_id', $list->id)->pluck('movie_id')->all());
        $this->assertSame('2026-11-03T18:20:00Z', $list->fresh()->updated_at->toIso8601ZuluString());
    }

    public function test_removing_a_movie_only_touches_that_list(): void
    {
        $list = $this->list();
        $other = $this->list(['title' => 'Other']);
        $this->addMovie($list);
        $this->addMovie($other);

        $this->json('DELETE', "/api/lists/{$list->id}/movies/".self::MOVIE, [], $this->auth())->seeStatusCode(204);

        $this->assertSame(1, $this->movieCount($other));
    }

    public function test_removing_a_movie_that_is_not_in_the_list_changes_nothing(): void
    {
        $list = $this->list();
        Carbon::setTestNow('2026-11-03T18:20:00Z');

        $this->json('DELETE', "/api/lists/{$list->id}/movies/".self::MOVIE, [], $this->auth())->seeStatusCode(204);

        $this->assertSame('2026-11-01T10:00:00Z', $list->fresh()->updated_at->toIso8601ZuluString());
    }

    public function test_another_user_gets_403_and_changes_nothing(): void
    {
        foreach ([true, false] as $isPublic) {
            $list = $this->list(['is_public' => $isPublic]);
            $this->addMovie($list);

            $this->json('POST', "/api/lists/{$list->id}/movies", ['movie_id' => self::OTHER_MOVIE], $this->auth(self::OTHER))->seeStatusCode(403);
            $this->json('DELETE', "/api/lists/{$list->id}/movies/".self::MOVIE, [], $this->auth(self::OTHER))->seeStatusCode(403);

            $this->assertSame(1, $this->movieCount($list));
        }
    }

    public function test_an_admin_is_not_the_owner(): void
    {
        $list = $this->list();

        $this->json('POST', "/api/lists/{$list->id}/movies", ['movie_id' => self::MOVIE], $this->auth(self::OTHER, 'admin'))->seeStatusCode(403);

        $this->assertSame(0, $this->movieCount($list));
    }

    public function test_another_user_gets_403_before_validation(): void
    {
        $list = $this->list();

        $this->json('POST', "/api/lists/{$list->id}/movies", ['movie_id' => 'abc'], $this->auth(self::OTHER))->seeStatusCode(403);
    }

    public function test_an_unknown_or_malformed_list_gives_404(): void
    {
        $id = (string) Str::uuid();

        $this->json('POST', "/api/lists/$id/movies", ['movie_id' => self::MOVIE], $this->auth())->seeStatusCode(404)->seeJsonEquals(['message' => 'List not found.']);
        $this->json('DELETE', "/api/lists/$id/movies/".self::MOVIE, [], $this->auth())->seeStatusCode(404);
        $this->json('POST', '/api/lists/abc/movies', ['movie_id' => self::MOVIE], $this->auth())->seeStatusCode(404);
    }

    public function test_a_list_shows_its_movie_ids_in_the_order_they_were_added(): void
    {
        $list = $this->list(['is_public' => true]);
        $this->addMovie($list, self::THIRD_MOVIE, '2026-11-02T10:00:00Z');
        $this->addMovie($list, self::MOVIE, '2026-11-03T10:00:00Z');
        $this->addMovie($list, self::OTHER_MOVIE, '2026-11-01T10:00:00Z');

        $this->get("/api/lists/{$list->id}")->seeStatusCode(200);

        $this->assertSame([self::OTHER_MOVIE, self::THIRD_MOVIE, self::MOVIE], $this->body()['data']['movie_ids']);
    }

    public function test_an_empty_list_shows_no_movie_ids(): void
    {
        $list = $this->list(['is_public' => true]);

        $this->get("/api/lists/{$list->id}")->seeStatusCode(200);

        $this->assertSame([], $this->body()['data']['movie_ids']);
    }

    public function test_the_movies_of_a_private_list_stay_hidden_from_others(): void
    {
        $list = $this->list();
        $this->addMovie($list);

        $this->get("/api/lists/{$list->id}")->seeStatusCode(404)->seeJsonEquals(['message' => 'List not found.']);
        $this->newRequest()->get("/api/lists/{$list->id}", $this->auth(self::OTHER))->seeStatusCode(404)->seeJsonEquals(['message' => 'List not found.']);

        $this->newRequest()->get("/api/lists/{$list->id}", $this->auth())->seeStatusCode(200);
        $this->assertSame([self::MOVIE], $this->body()['data']['movie_ids']);
    }

    public function test_other_list_responses_do_not_carry_movie_ids(): void
    {
        $list = $this->list(['is_public' => true]);
        $this->addMovie($list);

        foreach (['/api/lists' => $this->auth(), '/api/users/'.self::ME.'/lists' => []] as $uri => $headers) {
            $this->get($uri, $headers)->seeStatusCode(200);
            $this->assertArrayNotHasKey('movie_ids', $this->body()['data'][0]);
        }

        $this->json('PUT', "/api/lists/{$list->id}", ['title' => 'After'], $this->auth())->seeStatusCode(200);
        $this->assertArrayNotHasKey('movie_ids', $this->body()['data']);
    }
}
