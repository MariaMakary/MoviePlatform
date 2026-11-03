<?php

namespace Tests;

use App\Models\MovieList;
use Firebase\JWT\JWT;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Lumen\Testing\DatabaseTransactions;
use MoviePlatform\Common\Integration\RabbitMqConnection;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;

class ListApiTest extends TestCase
{
    use DatabaseTransactions;

    private const ME = '0193a7c0-1111-7aaa-8bbb-000000000001';

    private const OTHER = '0193a7c0-2222-7aaa-8bbb-000000000002';

    private const MOVIE = '0193a7c1-3333-7aaa-8bbb-000000000100';

    private const OTHER_MOVIE = '0193a7c1-3333-7aaa-8bbb-000000000200';

    private array $published = [];

    protected function setUp(): void
    {
        parent::setUp();

        config(['jwt.public_key_path' => base_path('tests/Fixtures/jwt/test-public.pem')]);

        $channel = $this->createMock(AMQPChannel::class);
        $channel->method('basic_publish')->willReturnCallback(function (AMQPMessage $message, string $exchange, string $routingKey) {
            $this->published[] = [$routingKey, json_decode($message->getBody(), true), DB::transactionLevel()];
        });

        $rabbitMq = $this->createMock(RabbitMqConnection::class);
        $rabbitMq->method('channel')->willReturn($channel);
        $rabbitMq->method('exchange')->willReturn('movieplatform.events');
        $this->app->instance(RabbitMqConnection::class, $rabbitMq);
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
        return MovieList::forceCreate(array_merge(['user_id' => self::ME, 'title' => 'Best of Nolan'], $attributes));
    }

    private function addMovie(MovieList $list, string $movieId = self::MOVIE): void
    {
        DB::table('list_movie')->insert(['list_id' => $list->id, 'movie_id' => $movieId, 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()]);
    }

    private function body(): array
    {
        return json_decode($this->response->getContent(), true);
    }

    public function test_the_tables_match_the_erd(): void
    {
        $columns = fn (string $table) => collect(DB::select(
            'select column_name, data_type, is_nullable, character_maximum_length as length from information_schema.columns
             where table_schema = current_schema() and table_name = ? order by ordinal_position',
            [$table]
        ))->mapWithKeys(fn ($c) => [$c->column_name => $c->data_type.($c->length ? "($c->length)" : '').($c->is_nullable === 'YES' ? ' NULL' : '')])->all();

        $this->assertSame([
            'id' => 'uuid',
            'user_id' => 'uuid',
            'title' => 'character varying(150)',
            'description' => 'text NULL',
            'author_blocked' => 'boolean',
            'is_public' => 'boolean',
            'created_at' => 'timestamp without time zone NULL',
            'updated_at' => 'timestamp without time zone NULL',
        ], $columns('lists'));

        $this->assertSame([
            'list_id' => 'uuid',
            'movie_id' => 'uuid',
            'created_at' => 'timestamp without time zone NULL',
            'updated_at' => 'timestamp without time zone NULL',
        ], $columns('list_movie'));

        $indexes = collect(DB::select("select indexname from pg_indexes where tablename in ('lists', 'list_movie')"))->pluck('indexname');
        $this->assertContains('lists_user_id_index', $indexes);
        $this->assertContains('list_movie_movie_id_index', $indexes);
        $this->assertContains('list_movie_pkey', $indexes);
    }

    public function test_changing_routes_need_a_token(): void
    {
        $id = (string) Str::uuid();

        foreach ([['GET', '/api/lists'], ['POST', '/api/lists'], ['PUT', "/api/lists/$id"], ['DELETE', "/api/lists/$id"]] as [$method, $uri]) {
            $this->json($method, $uri)->seeStatusCode(401)->seeJsonEquals(['message' => 'Unauthenticated.']);
        }
    }

    public function test_my_lists_are_mine_only_newest_first_and_include_private_ones(): void
    {
        $old = $this->list(['title' => 'Old', 'is_public' => true, 'created_at' => Carbon::parse('2026-11-01T10:00:00Z')]);
        $new = $this->list(['title' => 'New', 'created_at' => Carbon::parse('2026-11-02T10:00:00Z')]);
        $this->list(['user_id' => self::OTHER, 'is_public' => true]);

        $this->get('/api/lists', $this->auth())->seeStatusCode(200);

        $body = $this->body();
        $this->assertSame([$new->id, $old->id], array_column($body['data'], 'id'));
        $this->assertSame(['current_page' => 1, 'last_page' => 1, 'total' => 2], $body['meta']);
        $this->assertArrayNotHasKey('contains_movie', $body['data'][0]);
        $this->assertArrayNotHasKey('author_blocked', $body['data'][0]);
    }

    public function test_my_lists_are_paginated(): void
    {
        foreach (range(1, 3) as $i) {
            $this->list(['title' => "List $i"]);
        }

        $this->get('/api/lists?per_page=2&page=2', $this->auth())->seeStatusCode(200);

        $this->assertCount(1, $this->body()['data']);
        $this->assertSame(['current_page' => 2, 'last_page' => 2, 'total' => 3], $this->body()['meta']);
    }

    public function test_with_movie_id_each_list_says_if_it_contains_the_movie(): void
    {
        $with = $this->list(['title' => 'With']);
        $without = $this->list(['title' => 'Without']);
        $this->addMovie($with);
        $this->addMovie($without, self::OTHER_MOVIE);

        $this->get('/api/lists?movie_id='.self::MOVIE, $this->auth())->seeStatusCode(200);

        $contains = collect($this->body()['data'])->pluck('contains_movie', 'id');
        $this->assertTrue($contains[$with->id]);
        $this->assertFalse($contains[$without->id]);
    }

    public function test_an_invalid_movie_id_is_refused(): void
    {
        $this->get('/api/lists?movie_id=abc', $this->auth())->seeStatusCode(422)->seeJsonStructure(['message', 'errors' => ['movie_id']]);
    }

    public function test_creating_a_list_defaults_to_private_and_belongs_to_the_token_user(): void
    {
        $this->json('POST', '/api/lists', ['title' => 'Comedies', 'user_id' => self::OTHER, 'author_blocked' => true], $this->auth())
            ->seeStatusCode(201);

        $data = $this->body()['data'];
        $this->assertTrue(Str::isUuid($data['id']));
        $this->assertSame(self::ME, $data['user_id']);
        $this->assertSame('Comedies', $data['title']);
        $this->assertNull($data['description']);
        $this->assertFalse($data['is_public']);
        $this->assertFalse(MovieList::findOrFail($data['id'])->author_blocked);
    }

    public function test_list_created_is_published_with_the_agreed_payload_after_commit(): void
    {
        $this->json('POST', '/api/lists', ['title' => 'Best of Nolan', 'description' => 'Mind benders', 'is_public' => true], $this->auth())
            ->seeStatusCode(201);

        $this->assertCount(1, $this->published);
        [$routingKey, $envelope, $transactionLevel] = $this->published[0];
        $this->assertSame('list.created', $routingKey);
        $this->assertSame('ListCreated', $envelope['event']);
        $this->assertSame([
            'list_id' => $this->body()['data']['id'],
            'user_id' => self::ME,
            'title' => 'Best of Nolan',
            'is_public' => true,
        ], $envelope['data']);
        $this->assertSame(1, $transactionLevel, 'Only the test transaction may be open when the event is published.');
    }

    public function test_a_private_list_also_publishes_list_created(): void
    {
        $this->json('POST', '/api/lists', ['title' => 'Secret'], $this->auth())->seeStatusCode(201);

        $this->assertFalse($this->published[0][1]['data']['is_public']);
    }

    public function test_invalid_lists_are_refused_and_publish_nothing(): void
    {
        foreach ([
            [[], 'title'],
            [['title' => '   '], 'title'],
            [['title' => str_repeat('a', 151)], 'title'],
            [['title' => 'Ok', 'description' => str_repeat('a', 501)], 'description'],
            [['title' => 'Ok', 'is_public' => 'maybe'], 'is_public'],
        ] as [$payload, $field]) {
            $this->json('POST', '/api/lists', $payload, $this->auth())->seeStatusCode(422)->seeJsonStructure(['message', 'errors' => [$field]]);
        }

        $this->assertSame([], $this->published);
    }

    public function test_the_limits_are_accepted(): void
    {
        $this->json('POST', '/api/lists', ['title' => str_repeat('a', 150), 'description' => str_repeat('a', 500)], $this->auth())
            ->seeStatusCode(201);
    }

    public function test_the_owner_can_update_a_list_without_a_new_event(): void
    {
        $list = $this->list(['description' => 'Before']);

        $this->json('PUT', "/api/lists/{$list->id}", ['title' => 'After', 'description' => null, 'is_public' => true], $this->auth())
            ->seeStatusCode(200);

        $data = $this->body()['data'];
        $this->assertSame('After', $data['title']);
        $this->assertNull($data['description']);
        $this->assertTrue($data['is_public']);
        $this->assertSame([], $this->published);
    }

    public function test_an_update_keeps_the_fields_it_does_not_send(): void
    {
        $list = $this->list(['description' => 'Kept', 'is_public' => true]);

        $this->json('PUT', "/api/lists/{$list->id}", ['title' => 'After'], $this->auth())->seeStatusCode(200);

        $this->assertSame('Kept', $this->body()['data']['description']);
        $this->assertTrue($this->body()['data']['is_public']);
    }

    public function test_an_invalid_update_is_refused(): void
    {
        $list = $this->list();

        $this->json('PUT', "/api/lists/{$list->id}", ['title' => ''], $this->auth())->seeStatusCode(422)->seeJsonStructure(['errors' => ['title']]);
        $this->assertSame('Best of Nolan', $list->fresh()->title);
    }

    public function test_an_update_cannot_change_the_owner_or_author_blocked(): void
    {
        $list = $this->list();

        $this->json('PUT', "/api/lists/{$list->id}", ['user_id' => self::OTHER, 'author_blocked' => true], $this->auth())->seeStatusCode(200);

        $this->assertSame(self::ME, $list->fresh()->user_id);
        $this->assertFalse($list->fresh()->author_blocked);
    }

    public function test_only_the_owner_can_change_or_delete_a_list(): void
    {
        foreach ([true, false] as $isPublic) {
            $list = $this->list(['is_public' => $isPublic]);

            $this->json('PUT', "/api/lists/{$list->id}", ['title' => 'Hacked'], $this->auth(self::OTHER))->seeStatusCode(403);
            $this->json('DELETE', "/api/lists/{$list->id}", [], $this->auth(self::OTHER))->seeStatusCode(403);

            $this->assertSame('Best of Nolan', $list->fresh()->title);
        }
    }

    public function test_an_admin_is_not_the_owner(): void
    {
        $list = $this->list();

        $this->json('PUT', "/api/lists/{$list->id}", ['title' => 'Hacked'], $this->auth(self::OTHER, 'admin'))->seeStatusCode(403);
        $this->json('DELETE', "/api/lists/{$list->id}", [], $this->auth(self::OTHER, 'admin'))->seeStatusCode(403);
    }

    public function test_deleting_a_list_removes_its_items_only(): void
    {
        $list = $this->list();
        $kept = $this->list(['title' => 'Kept']);
        $this->addMovie($list);
        $this->addMovie($kept);

        $this->json('DELETE', "/api/lists/{$list->id}", [], $this->auth())->seeStatusCode(204);
        $this->assertSame('', $this->response->getContent());

        $this->assertNull(MovieList::find($list->id));
        $this->assertSame(0, DB::table('list_movie')->where('list_id', $list->id)->count());
        $this->assertSame(1, DB::table('list_movie')->where('list_id', $kept->id)->count());
    }

    public function test_changing_an_unknown_or_malformed_list_gives_404(): void
    {
        $id = (string) Str::uuid();

        $this->json('PUT', "/api/lists/$id", ['title' => 'x'], $this->auth())->seeStatusCode(404)->seeJsonEquals(['message' => 'List not found.']);
        $this->json('DELETE', "/api/lists/$id", [], $this->auth())->seeStatusCode(404);
        $this->json('DELETE', '/api/lists/abc', [], $this->auth())->seeStatusCode(404);
    }

    public function test_a_public_list_is_visible_to_guests_and_other_users(): void
    {
        $list = $this->list(['is_public' => true, 'description' => 'Mind benders']);

        $this->get("/api/lists/{$list->id}")->seeStatusCode(200)->seeJson(['id' => $list->id, 'title' => 'Best of Nolan', 'description' => 'Mind benders']);
        $this->get("/api/lists/{$list->id}", $this->auth(self::OTHER))->seeStatusCode(200);
    }

    public function test_a_private_list_gives_404_to_everyone_but_its_owner(): void
    {
        $list = $this->list();

        $this->get("/api/lists/{$list->id}")->seeStatusCode(404)->seeJsonEquals(['message' => 'List not found.']);
        $this->newRequest()->get("/api/lists/{$list->id}", $this->auth(self::OTHER))->seeStatusCode(404)->seeJsonEquals(['message' => 'List not found.']);
        $this->newRequest()->get("/api/lists/{$list->id}", $this->auth(self::OTHER, 'admin'))->seeStatusCode(404);
        $this->newRequest()->get("/api/lists/{$list->id}", $this->auth())->seeStatusCode(200);
    }

    public function test_a_list_of_a_blocked_author_gives_404_to_everyone_but_its_owner(): void
    {
        $list = $this->list(['is_public' => true, 'author_blocked' => true]);

        $this->get("/api/lists/{$list->id}")->seeStatusCode(404);
        $this->newRequest()->get("/api/lists/{$list->id}", $this->auth(self::OTHER))->seeStatusCode(404);
        $this->newRequest()->get("/api/lists/{$list->id}", $this->auth())->seeStatusCode(200);
    }

    public function test_an_invalid_token_on_a_guest_route_is_treated_as_a_guest(): void
    {
        $list = $this->list();

        $this->get("/api/lists/{$list->id}", ['Authorization' => 'Bearer not-a-token'])->seeStatusCode(404);
    }

    public function test_an_unknown_or_malformed_list_gives_404(): void
    {
        $this->get('/api/lists/'.Str::uuid())->seeStatusCode(404)->seeJsonEquals(['message' => 'List not found.']);
        $this->get('/api/lists/abc')->seeStatusCode(404);
    }

    public function test_a_users_public_lists_hide_private_and_blocked_ones(): void
    {
        $public = $this->list(['title' => 'Public', 'is_public' => true]);
        $this->list(['title' => 'Private']);
        $this->list(['title' => 'Blocked', 'is_public' => true, 'author_blocked' => true]);
        $this->list(['title' => 'Someone else', 'user_id' => self::OTHER, 'is_public' => true]);

        foreach ([[], $this->auth(), $this->auth(self::OTHER)] as $headers) {
            $this->get('/api/users/'.self::ME.'/lists', $headers)->seeStatusCode(200);

            $this->assertSame([$public->id], array_column($this->body()['data'], 'id'));
            $this->assertSame(['current_page' => 1, 'last_page' => 1, 'total' => 1], $this->body()['meta']);
        }
    }

    public function test_a_user_without_public_lists_gives_an_empty_page(): void
    {
        $this->get('/api/users/'.Str::uuid().'/lists')->seeStatusCode(200);

        $this->assertSame([], $this->body()['data']);
    }
}
