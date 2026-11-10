<?php

namespace Tests;

use App\Models\WatchlistItem;
use Firebase\JWT\JWT;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Lumen\Testing\DatabaseTransactions;

class WatchlistApiTest extends TestCase
{
    use DatabaseTransactions;

    private const ME = '0193a7c0-1111-7aaa-8bbb-000000000001';

    private const OTHER = '0193a7c0-2222-7aaa-8bbb-000000000002';

    private const MOVIE = '0193a7c1-3333-7aaa-8bbb-000000000100';

    private const OTHER_MOVIE = '0193a7c1-3333-7aaa-8bbb-000000000200';

    protected function setUp(): void
    {
        parent::setUp();

        config(['jwt.public_key_path' => base_path('tests/Fixtures/jwt/test-public.pem')]);
    }

    private function auth(string $userId = self::ME): array
    {
        $token = JWT::encode(
            ['sub' => $userId, 'role' => 'user', 'name' => 'Tonio Jreige', 'exp' => time() + 1800],
            file_get_contents(base_path('tests/Fixtures/jwt/test-private.pem')),
            'RS256',
        );

        return ['Authorization' => "Bearer $token"];
    }

    private function item(string $movieId = self::MOVIE, string $userId = self::ME, array $attributes = []): WatchlistItem
    {
        return WatchlistItem::forceCreate(array_merge(['user_id' => $userId, 'movie_id' => $movieId], $attributes));
    }

    public function test_the_table_matches_the_erd(): void
    {
        $columns = collect(DB::select(
            "select column_name, data_type, is_nullable from information_schema.columns
             where table_schema = current_schema() and table_name = 'watchlist' order by ordinal_position"
        ))->mapWithKeys(fn ($c) => [$c->column_name => $c->data_type.($c->is_nullable === 'YES' ? ' NULL' : '')])->all();

        $this->assertSame([
            'id' => 'uuid',
            'user_id' => 'uuid',
            'movie_id' => 'uuid',
            'created_at' => 'timestamp without time zone NULL',
            'updated_at' => 'timestamp without time zone NULL',
        ], $columns);

        $indexes = collect(DB::select("select indexname from pg_indexes where tablename = 'watchlist'"))->pluck('indexname');
        $this->assertContains('watchlist_user_id_movie_id_unique', $indexes);
    }

    public function test_every_route_needs_a_token(): void
    {
        foreach ([['GET', '/api/watchlist'], ['GET', '/api/watchlist/contains?ids='.self::MOVIE],
            ['POST', '/api/watchlist'], ['DELETE', '/api/watchlist/'.self::MOVIE]] as [$method, $uri]) {
            $this->json($method, $uri)
                ->seeStatusCode(401)
                ->seeJsonEquals(['message' => 'Unauthenticated.']);
        }
    }

    public function test_list_shows_only_my_movies_newest_first(): void
    {
        $old = $this->item(self::MOVIE, attributes: ['created_at' => Carbon::parse('2026-11-01T10:00:00Z')]);
        $new = $this->item(self::OTHER_MOVIE, attributes: ['created_at' => Carbon::parse('2026-11-02T10:00:00Z')]);
        $this->item(self::MOVIE, self::OTHER);

        $this->get('/api/watchlist', $this->auth())
            ->seeStatusCode(200)
            ->seeJsonEquals([
                'data' => [
                    ['id' => $new->id, 'movie_id' => self::OTHER_MOVIE, 'created_at' => '2026-11-02T10:00:00Z'],
                    ['id' => $old->id, 'movie_id' => self::MOVIE, 'created_at' => '2026-11-01T10:00:00Z'],
                ],
                'meta' => ['current_page' => 1, 'last_page' => 1, 'total' => 2],
            ]);
    }

    public function test_list_is_paginated(): void
    {
        $movies = [];
        foreach (range(1, 5) as $day) {
            $movies[$day] = (string) Str::uuid();
            $this->item($movies[$day], attributes: ['created_at' => Carbon::parse("2026-11-0{$day}T10:00:00Z")]);
        }

        $this->get('/api/watchlist?per_page=2&page=2', $this->auth())
            ->seeStatusCode(200)
            ->seeJson(['meta' => ['current_page' => 2, 'last_page' => 3, 'total' => 5]]);

        $this->assertSame([$movies[3], $movies[2]], array_column($this->response->getData(true)['data'], 'movie_id'));
    }

    public function test_i_can_add_a_movie(): void
    {
        Carbon::setTestNow('2026-11-03T18:20:00Z');

        $this->json('POST', '/api/watchlist', ['movie_id' => self::MOVIE], $this->auth())
            ->seeStatusCode(201)
            ->seeJson(['movie_id' => self::MOVIE, 'created_at' => '2026-11-03T18:20:00Z']);

        $this->assertSame(1, WatchlistItem::ownedBy(self::ME)->where('movie_id', self::MOVIE)->count());

        Carbon::setTestNow();
    }

    public function test_adding_a_movie_twice_gives_one_row_and_no_error(): void
    {
        $item = $this->item();

        $this->json('POST', '/api/watchlist', ['movie_id' => self::MOVIE], $this->auth())
            ->seeStatusCode(200)
            ->seeJson(['id' => $item->id, 'movie_id' => self::MOVIE]);

        $this->assertSame(1, WatchlistItem::ownedBy(self::ME)->count());
    }

    public function test_two_users_can_add_the_same_movie(): void
    {
        $this->item(self::MOVIE, self::OTHER);

        $this->json('POST', '/api/watchlist', ['movie_id' => self::MOVIE], $this->auth())->seeStatusCode(201);

        $this->assertSame(2, WatchlistItem::where('movie_id', self::MOVIE)->count());
    }

    public function test_adding_needs_a_valid_movie_id(): void
    {
        foreach ([[], ['movie_id' => 'not-a-uuid']] as $body) {
            $this->json('POST', '/api/watchlist', $body, $this->auth())
                ->seeStatusCode(422)
                ->seeJson(['message' => 'The given data was invalid.']);

            $this->assertArrayHasKey('movie_id', $this->response->getData(true)['errors']);
        }
    }

    public function test_i_can_remove_a_movie(): void
    {
        $this->item();
        $this->item(self::OTHER_MOVIE);

        $this->json('DELETE', '/api/watchlist/'.self::MOVIE, [], $this->auth())->seeStatusCode(204);

        $this->assertSame([self::OTHER_MOVIE], WatchlistItem::ownedBy(self::ME)->pluck('movie_id')->all());
    }

    public function test_removing_a_movie_that_is_not_there_still_answers_204(): void
    {
        $this->json('DELETE', '/api/watchlist/'.self::MOVIE, [], $this->auth())->seeStatusCode(204);
    }

    public function test_i_cannot_remove_a_movie_from_another_users_watchlist(): void
    {
        $this->item(self::MOVIE, self::OTHER);

        $this->json('DELETE', '/api/watchlist/'.self::MOVIE, [], $this->auth())->seeStatusCode(204);

        $this->assertSame(1, WatchlistItem::ownedBy(self::OTHER)->count());
    }

    public function test_removing_with_a_malformed_id_is_rejected(): void
    {
        $this->json('DELETE', '/api/watchlist/not-a-uuid', [], $this->auth())->seeStatusCode(422);

        $this->assertArrayHasKey('movie_id', $this->response->getData(true)['errors']);
    }

    public function test_contains_returns_only_the_movies_in_my_watchlist(): void
    {
        $this->item();
        $this->item(self::OTHER_MOVIE, self::OTHER);
        $absent = (string) Str::uuid();

        $this->get('/api/watchlist/contains?ids='.self::MOVIE.','.self::OTHER_MOVIE.",$absent", $this->auth())
            ->seeStatusCode(200)
            ->seeJsonEquals(['data' => [self::MOVIE]]);
    }

    public function test_contains_answers_an_empty_list_when_nothing_matches(): void
    {
        $this->get('/api/watchlist/contains?ids='.self::MOVIE, $this->auth())
            ->seeStatusCode(200)
            ->seeJsonEquals(['data' => []]);
    }

    public function test_contains_accepts_100_ids_but_not_101(): void
    {
        $ids = array_map(fn () => (string) Str::uuid(), range(1, 101));

        $this->get('/api/watchlist/contains?ids='.implode(',', array_slice($ids, 0, 100)), $this->auth())->seeStatusCode(200);
        $this->get('/api/watchlist/contains?ids='.implode(',', $ids), $this->auth())->seeStatusCode(422);
    }

    public function test_contains_needs_valid_ids(): void
    {
        foreach (['', '?ids=', '?ids=not-a-uuid', '?ids[]='.self::MOVIE] as $query) {
            $this->get("/api/watchlist/contains$query", $this->auth())->seeStatusCode(422);
        }
    }
}
