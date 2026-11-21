<?php

namespace Tests;

use App\Models\Genre;
use App\Models\Movie;
use Firebase\JWT\JWT;
use Laravel\Lumen\Testing\DatabaseTransactions;

class GenreCountsTest extends TestCase
{
    use DatabaseTransactions;

    private const UNKNOWN = '0193a7c1-9999-7aaa-8bbb-000000000999';

    protected function setUp(): void
    {
        parent::setUp();

        config(['jwt.public_key_path' => base_path('tests/Fixtures/jwt/test-public.pem')]);
    }

    private function auth(string $role = 'admin'): array
    {
        $this->app['auth']->forgetGuards();

        $token = JWT::encode(
            ['sub' => '0193a7c0-1111-7aaa-8bbb-000000000001', 'role' => $role, 'name' => 'Someone', 'exp' => time() + 1800],
            file_get_contents(base_path('tests/Fixtures/jwt/test-private.pem')),
            'RS256',
        );

        return ['Authorization' => "Bearer $token"];
    }

    private function counts(mixed $movieCounts, string $role = 'admin'): void
    {
        $this->json('POST', '/api/admin/genre-counts', ['movie_counts' => $movieCounts], $this->auth($role));
    }

    private function movieIn(Genre ...$genres): Movie
    {
        $movie = Movie::factory()->create();
        $movie->genres()->attach(collect($genres)->pluck('id')->all());

        return $movie;
    }

    public function test_a_movie_with_two_genres_counts_for_both_and_totals_come_highest_first(): void
    {
        $action = Genre::factory()->create(['name' => 'Zz Test Action']);
        $drama = Genre::factory()->create(['name' => 'Zz Test Drama']);
        $comedy = Genre::factory()->create(['name' => 'Zz Test Comedy']);

        $both = $this->movieIn($action, $drama);
        $dramaOnly = $this->movieIn($drama);
        $comedyOnly = $this->movieIn($comedy);
        $noGenre = $this->movieIn();

        $this->counts([
            $both->id => 10,
            $dramaOnly->id => 5,
            strtoupper($comedyOnly->id) => 10,
            $noGenre->id => 7,
            self::UNKNOWN => 99,
        ]);

        $this->seeStatusCode(200);
        $this->seeJsonEquals(['data' => [
            ['genre' => 'Zz Test Drama', 'count' => 15],
            ['genre' => 'Zz Test Action', 'count' => 10],
            ['genre' => 'Zz Test Comedy', 'count' => 10],
        ]]);
    }

    public function test_unknown_movies_zero_counts_and_an_empty_object_give_an_empty_list(): void
    {
        $genre = Genre::factory()->create(['name' => 'Zz Test Horror']);
        $movie = $this->movieIn($genre);

        $this->counts([self::UNKNOWN => 12, $movie->id => 0]);
        $this->seeStatusCode(200);
        $this->seeJsonEquals(['data' => []]);

        $this->json('POST', '/api/admin/genre-counts', json_decode('{"movie_counts": {}}', true), $this->auth());
        $this->seeStatusCode(200);
        $this->seeJsonEquals(['data' => []]);
    }

    public function test_invalid_bodies_give_422(): void
    {
        $movie = Movie::factory()->create();
        $tooMany = collect(range(1, 1001))->mapWithKeys(fn (int $i) => [sprintf('0193a7c1-0000-7aaa-8bbb-%012d', $i) => 1])->all();

        foreach ([null, 'x', ['not-a-uuid' => 1], [$movie->id => -1], [$movie->id => 'many'], $tooMany] as $movieCounts) {
            $this->counts($movieCounts);
            $this->seeStatusCode(422);
            $this->assertNotEmpty(array_filter(
                array_keys(json_decode($this->response->getContent(), true)['errors']),
                fn (string $key) => str_starts_with($key, 'movie_counts'),
            ));
        }

        $this->json('POST', '/api/admin/genre-counts', [], $this->auth());
        $this->seeStatusCode(422);
    }

    public function test_a_normal_user_gets_403_and_a_guest_401(): void
    {
        $this->counts([], 'user');
        $this->seeStatusCode(403);
        $this->seeJsonEquals(['message' => 'This action is unauthorized.']);

        $this->app['auth']->forgetGuards();
        $this->json('POST', '/api/admin/genre-counts', ['movie_counts' => []]);
        $this->seeStatusCode(401);
    }
}
