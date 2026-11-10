<?php

namespace Tests;

use App\Models\WatchHistoryEntry;
use App\Models\WatchlistItem;
use Firebase\JWT\JWT;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Lumen\Testing\DatabaseTransactions;
use MoviePlatform\Common\Integration\RabbitMqConnection;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;

class HistoryApiTest extends TestCase
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

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
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

    private function entry(string $watchedAt, string $movieId = self::MOVIE, string $userId = self::ME): WatchHistoryEntry
    {
        return WatchHistoryEntry::forceCreate([
            'user_id' => $userId,
            'movie_id' => $movieId,
            'watched_at' => Carbon::parse($watchedAt),
        ]);
    }

    public function test_the_table_matches_the_erd(): void
    {
        $columns = collect(DB::select(
            "select column_name, data_type, is_nullable from information_schema.columns
             where table_schema = current_schema() and table_name = 'watch_history' order by ordinal_position"
        ))->mapWithKeys(fn ($c) => [$c->column_name => $c->data_type.($c->is_nullable === 'YES' ? ' NULL' : '')])->all();

        $this->assertSame([
            'id' => 'uuid',
            'user_id' => 'uuid',
            'movie_id' => 'uuid',
            'watched_at' => 'timestamp without time zone',
        ], $columns);

        $indexes = collect(DB::select("select indexname from pg_indexes where tablename = 'watch_history'"))->pluck('indexname');
        $this->assertContains('watch_history_user_id_watched_at_index', $indexes);
    }

    public function test_every_route_needs_a_token(): void
    {
        foreach ([['GET', '/api/history'], ['GET', '/api/history/contains?ids='.self::MOVIE], ['POST', '/api/history']] as [$method, $uri]) {
            $this->json($method, $uri)
                ->seeStatusCode(401)
                ->seeJsonEquals(['message' => 'Unauthenticated.']);
        }
    }

    public function test_watched_at_defaults_to_now(): void
    {
        Carbon::setTestNow('2026-11-03T18:20:00Z');

        $this->json('POST', '/api/history', ['movie_id' => self::MOVIE], $this->auth())
            ->seeStatusCode(201)
            ->seeJson(['movie_id' => self::MOVIE, 'watched_at' => '2026-11-03T18:20:00Z']);
    }

    public function test_watched_at_is_stored_in_utc(): void
    {
        Carbon::setTestNow('2026-11-03T18:20:00Z');

        $this->json('POST', '/api/history', ['movie_id' => self::MOVIE, 'watched_at' => '2026-11-02T23:00:00+02:00'], $this->auth())
            ->seeStatusCode(201)
            ->seeJson(['watched_at' => '2026-11-02T21:00:00Z']);

        $this->assertSame('2026-11-02 21:00:00', DB::table('watch_history')->where('user_id', self::ME)->value('watched_at'));
    }

    public function test_marking_as_watched_removes_the_movie_from_my_watchlist_only(): void
    {
        WatchlistItem::forceCreate(['user_id' => self::ME, 'movie_id' => self::MOVIE]);
        WatchlistItem::forceCreate(['user_id' => self::ME, 'movie_id' => self::OTHER_MOVIE]);
        WatchlistItem::forceCreate(['user_id' => self::OTHER, 'movie_id' => self::MOVIE]);

        $this->json('POST', '/api/history', ['movie_id' => self::MOVIE], $this->auth())->seeStatusCode(201);

        $this->assertSame([self::OTHER_MOVIE], WatchlistItem::ownedBy(self::ME)->pluck('movie_id')->all());
        $this->assertSame(1, WatchlistItem::ownedBy(self::OTHER)->count());
    }

    public function test_movie_watched_is_published_with_the_agreed_payload_after_commit(): void
    {
        $this->json('POST', '/api/history', ['movie_id' => self::MOVIE, 'watched_at' => '2026-10-30T21:00:00Z'], $this->auth())
            ->seeStatusCode(201);

        $this->assertCount(1, $this->published);
        [$routingKey, $envelope, $transactionLevel] = $this->published[0];
        $this->assertSame('movie.watched', $routingKey);
        $this->assertSame('MovieWatched', $envelope['event']);
        $this->assertSame(['user_id' => self::ME, 'movie_id' => self::MOVIE, 'watched_at' => '2026-10-30T21:00:00Z'], $envelope['data']);
        $this->assertSame(1, $transactionLevel, 'Only the test transaction may be open when the event is published.');
    }

    public function test_a_movie_can_be_watched_again(): void
    {
        foreach (['2026-10-01T21:00:00Z', '2026-10-02T21:00:00Z'] as $watchedAt) {
            $this->json('POST', '/api/history', ['movie_id' => self::MOVIE, 'watched_at' => $watchedAt], $this->auth())
                ->seeStatusCode(201);
        }

        $this->assertSame(2, WatchHistoryEntry::ownedBy(self::ME)->where('movie_id', self::MOVIE)->count());
        $this->assertCount(2, $this->published);
    }

    public function test_a_future_date_is_rejected_and_changes_nothing(): void
    {
        WatchlistItem::forceCreate(['user_id' => self::ME, 'movie_id' => self::MOVIE]);

        $this->json('POST', '/api/history', ['movie_id' => self::MOVIE, 'watched_at' => Carbon::now()->addDay()->toIso8601ZuluString()], $this->auth())
            ->seeStatusCode(422);

        $this->assertArrayHasKey('watched_at', $this->response->getData(true)['errors']);
        $this->assertSame(0, WatchHistoryEntry::ownedBy(self::ME)->count());
        $this->assertSame(1, WatchlistItem::ownedBy(self::ME)->count());
        $this->assertSame([], $this->published);
    }

    public function test_store_validates_its_fields(): void
    {
        foreach ([[[], 'movie_id'], [['movie_id' => 'not-a-uuid'], 'movie_id'], [['movie_id' => self::MOVIE, 'watched_at' => 'yesterday-ish'], 'watched_at']] as [$body, $field]) {
            $this->json('POST', '/api/history', $body, $this->auth())->seeStatusCode(422);

            $this->assertArrayHasKey($field, $this->response->getData(true)['errors']);
        }

        $this->assertSame([], $this->published);
    }

    public function test_list_shows_only_my_entries_newest_watched_first(): void
    {
        $old = $this->entry('2026-11-01T10:00:00Z');
        $new = $this->entry('2026-11-02T10:00:00Z', self::OTHER_MOVIE);
        $this->entry('2026-11-03T10:00:00Z', self::MOVIE, self::OTHER);

        $this->get('/api/history', $this->auth())
            ->seeStatusCode(200)
            ->seeJsonEquals([
                'data' => [
                    ['id' => $new->id, 'movie_id' => self::OTHER_MOVIE, 'watched_at' => '2026-11-02T10:00:00Z'],
                    ['id' => $old->id, 'movie_id' => self::MOVIE, 'watched_at' => '2026-11-01T10:00:00Z'],
                ],
                'meta' => ['current_page' => 1, 'last_page' => 1, 'total' => 2],
            ]);
    }

    public function test_list_is_paginated(): void
    {
        $movies = [];
        foreach (range(1, 5) as $day) {
            $movies[$day] = (string) Str::uuid();
            $this->entry("2026-11-0{$day}T10:00:00Z", $movies[$day]);
        }

        $this->get('/api/history?per_page=2&page=2', $this->auth())
            ->seeStatusCode(200)
            ->seeJson(['meta' => ['current_page' => 2, 'last_page' => 3, 'total' => 5]]);

        $this->assertSame([$movies[3], $movies[2]], array_column($this->response->getData(true)['data'], 'movie_id'));
    }

    public function test_contains_returns_the_latest_watched_at_of_my_movies(): void
    {
        $this->entry('2026-11-01T10:00:00Z');
        $this->entry('2026-11-05T10:00:00Z');
        $this->entry('2026-11-03T10:00:00Z');
        $this->entry('2026-11-06T10:00:00Z', self::OTHER_MOVIE, self::OTHER);
        $absent = (string) Str::uuid();

        $this->get('/api/history/contains?ids='.self::MOVIE.','.self::OTHER_MOVIE.",$absent", $this->auth())
            ->seeStatusCode(200)
            ->seeJsonEquals(['data' => [['movie_id' => self::MOVIE, 'watched_at' => '2026-11-05T10:00:00Z']]]);
    }

    public function test_contains_answers_an_empty_list_when_nothing_matches(): void
    {
        $this->get('/api/history/contains?ids='.self::MOVIE, $this->auth())
            ->seeStatusCode(200)
            ->seeJsonEquals(['data' => []]);
    }

    public function test_contains_accepts_100_ids_but_not_101(): void
    {
        $ids = array_map(fn () => (string) Str::uuid(), range(1, 101));

        $this->get('/api/history/contains?ids='.implode(',', array_slice($ids, 0, 100)), $this->auth())->seeStatusCode(200);
        $this->get('/api/history/contains?ids='.implode(',', $ids), $this->auth())->seeStatusCode(422);
    }

    public function test_contains_needs_valid_ids(): void
    {
        foreach (['', '?ids=', '?ids=not-a-uuid', '?ids[]='.self::MOVIE] as $query) {
            $this->get("/api/history/contains$query", $this->auth())->seeStatusCode(422);
        }
    }
}
