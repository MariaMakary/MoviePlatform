<?php

namespace Tests;

use App\Models\Follow;
use Firebase\JWT\JWT;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Lumen\Testing\DatabaseTransactions;
use MoviePlatform\Common\Integration\RabbitMqConnection;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;

class FollowApiTest extends TestCase
{
    use DatabaseTransactions;

    private const ME = '0193a7c0-1111-7aaa-8bbb-000000000001';

    private const MARIA = '0193a7c0-2222-7aaa-8bbb-000000000002';

    private const TONIO = '0193a7c0-3333-7aaa-8bbb-000000000003';

    private array $published = [];

    protected function setUp(): void
    {
        parent::setUp();

        config(['jwt.public_key_path' => base_path('tests/Fixtures/jwt/test-public.pem')]);

        $channel = $this->createMock(AMQPChannel::class);
        $channel->method('basic_publish')->willReturnCallback(function (AMQPMessage $message, string $exchange, string $routingKey) {
            $this->published[] = [$routingKey, json_decode($message->getBody(), true)];
        });

        $rabbitMq = $this->createMock(RabbitMqConnection::class);
        $rabbitMq->method('channel')->willReturn($channel);
        $rabbitMq->method('exchange')->willReturn('movieplatform.events');
        $this->app->instance(RabbitMqConnection::class, $rabbitMq);
    }

    private function auth(string $userId = self::ME, string $name = 'Joe Rohban'): array
    {
        $token = JWT::encode(
            ['sub' => $userId, 'role' => 'user', 'name' => $name, 'exp' => time() + 1800],
            file_get_contents(base_path('tests/Fixtures/jwt/test-private.pem')),
            'RS256',
        );

        return ['Authorization' => "Bearer $token"];
    }

    private function follows(string $followerId, string $followingId, string $at = '2026-11-01T10:00:00Z'): void
    {
        DB::table('follows')->insert(['follower_id' => $followerId, 'following_id' => $followingId, 'created_at' => Carbon::parse($at)]);
    }

    public function test_the_table_matches_the_erd(): void
    {
        $columns = collect(DB::select(
            "select column_name, data_type, is_nullable from information_schema.columns
             where table_schema = current_schema() and table_name = 'follows' order by ordinal_position"
        ))->mapWithKeys(fn ($c) => [$c->column_name => $c->data_type.($c->is_nullable === 'YES' ? ' NULL' : '')])->all();

        $this->assertSame([
            'follower_id' => 'uuid',
            'following_id' => 'uuid',
            'created_at' => 'timestamp without time zone',
        ], $columns);

        $indexes = collect(DB::select("select indexname from pg_indexes where tablename = 'follows'"))->pluck('indexname');
        $this->assertContains('follows_pkey', $indexes);
        $this->assertContains('follows_following_id_index', $indexes);
    }

    public function test_the_database_refuses_following_yourself(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('follows_not_self_check');

        $this->follows(self::ME, self::ME);
    }

    public function test_i_can_follow_a_user(): void
    {
        Carbon::setTestNow('2026-11-01T12:00:00Z');

        $this->json('POST', '/api/follow/'.self::MARIA, [], $this->auth())
            ->seeStatusCode(201)
            ->seeJsonEquals(['data' => ['follower_id' => self::ME, 'following_id' => self::MARIA, 'created_at' => '2026-11-01T12:00:00Z']]);

        $this->assertTrue(Follow::followedBy(self::ME)->where('following_id', self::MARIA)->exists());

        Carbon::setTestNow();
    }

    public function test_following_publishes_user_followed_with_the_follower_name(): void
    {
        $this->json('POST', '/api/follow/'.self::MARIA, [], $this->auth(name: 'Joe Rohban'))->seeStatusCode(201);

        $this->assertCount(1, $this->published);
        [$routingKey, $envelope] = $this->published[0];
        $this->assertSame('user.followed', $routingKey);
        $this->assertSame('UserFollowed', $envelope['event']);
        $this->assertSame(['follower_id' => self::ME, 'follower_name' => 'Joe Rohban', 'following_id' => self::MARIA], $envelope['data']);
    }

    public function test_following_yourself_is_422_and_publishes_nothing(): void
    {
        $this->json('POST', '/api/follow/'.self::ME, [], $this->auth())
            ->seeStatusCode(422)
            ->seeJsonEquals(['message' => 'The given data was invalid.', 'errors' => ['user_id' => ['You cannot follow yourself.']]]);

        $this->assertSame([], $this->published);
    }

    public function test_following_twice_is_422_and_publishes_once(): void
    {
        $this->json('POST', '/api/follow/'.self::MARIA, [], $this->auth())->seeStatusCode(201);

        $this->json('POST', '/api/follow/'.self::MARIA, [], $this->auth())
            ->seeStatusCode(422)
            ->seeJsonEquals(['message' => 'The given data was invalid.', 'errors' => ['user_id' => ['You are already following this user.']]]);

        $this->assertSame(1, Follow::followedBy(self::ME)->count());
        $this->assertCount(1, $this->published);
    }

    public function test_a_malformed_user_id_is_422(): void
    {
        $this->json('POST', '/api/follow/not-a-uuid', [], $this->auth())
            ->seeStatusCode(422)
            ->seeJson(['errors' => ['user_id' => ['The user id must be a valid UUID.']]]);

        $this->json('GET', '/api/users/not-a-uuid/followers')->seeStatusCode(422);
    }

    public function test_following_and_unfollowing_need_a_token(): void
    {
        $this->json('POST', '/api/follow/'.self::MARIA)->seeStatusCode(401);
        $this->json('DELETE', '/api/follow/'.self::MARIA)->seeStatusCode(401);
    }

    public function test_i_can_unfollow_a_user(): void
    {
        $this->follows(self::ME, self::MARIA);
        $this->follows(self::TONIO, self::MARIA);

        $this->json('DELETE', '/api/follow/'.self::MARIA, [], $this->auth())->seeStatusCode(204);

        $this->assertFalse(Follow::followedBy(self::ME)->exists());
        $this->assertTrue(Follow::followedBy(self::TONIO)->exists());
    }

    public function test_unfollowing_someone_i_dont_follow_is_still_204(): void
    {
        $this->json('DELETE', '/api/follow/'.self::MARIA, [], $this->auth())->seeStatusCode(204);
    }

    public function test_followers_are_listed_newest_first(): void
    {
        $this->follows(self::MARIA, self::ME, '2026-11-01T10:00:00Z');
        $this->follows(self::TONIO, self::ME, '2026-11-01T11:00:00Z');
        $this->follows(self::TONIO, self::MARIA);

        $this->json('GET', '/api/users/'.self::ME.'/followers')
            ->seeStatusCode(200)
            ->seeJsonEquals([
                'data' => [
                    ['user_id' => self::TONIO, 'followed_at' => '2026-11-01T11:00:00Z'],
                    ['user_id' => self::MARIA, 'followed_at' => '2026-11-01T10:00:00Z'],
                ],
                'meta' => ['current_page' => 1, 'last_page' => 1, 'total' => 2],
            ]);
    }

    public function test_following_is_listed_newest_first(): void
    {
        $this->follows(self::ME, self::MARIA, '2026-11-01T10:00:00Z');
        $this->follows(self::ME, self::TONIO, '2026-11-01T11:00:00Z');
        $this->follows(self::MARIA, self::TONIO);

        $this->json('GET', '/api/users/'.self::ME.'/following')
            ->seeStatusCode(200)
            ->seeJsonEquals([
                'data' => [
                    ['user_id' => self::TONIO, 'followed_at' => '2026-11-01T11:00:00Z'],
                    ['user_id' => self::MARIA, 'followed_at' => '2026-11-01T10:00:00Z'],
                ],
                'meta' => ['current_page' => 1, 'last_page' => 1, 'total' => 2],
            ]);
    }

    public function test_lists_are_paginated(): void
    {
        $this->follows(self::MARIA, self::ME, '2026-11-01T10:00:00Z');
        $this->follows(self::TONIO, self::ME, '2026-11-01T11:00:00Z');

        $this->json('GET', '/api/users/'.self::ME.'/followers?per_page=1&page=2')
            ->seeStatusCode(200)
            ->seeJsonEquals([
                'data' => [['user_id' => self::MARIA, 'followed_at' => '2026-11-01T10:00:00Z']],
                'meta' => ['current_page' => 2, 'last_page' => 2, 'total' => 2],
            ]);
    }

    public function test_follow_stats_for_a_guest_have_no_is_following(): void
    {
        $this->follows(self::MARIA, self::ME);
        $this->follows(self::TONIO, self::ME);
        $this->follows(self::ME, self::MARIA);

        $this->json('GET', '/api/users/'.self::ME.'/follow-stats')
            ->seeStatusCode(200)
            ->seeJsonEquals(['data' => ['followers' => 2, 'following' => 1, 'is_following' => null]]);
    }

    public function test_follow_stats_tell_a_logged_in_user_if_they_follow(): void
    {
        $this->follows(self::ME, self::MARIA);

        $this->json('GET', '/api/users/'.self::MARIA.'/follow-stats', [], $this->auth())
            ->seeJsonEquals(['data' => ['followers' => 1, 'following' => 0, 'is_following' => true]]);

        $this->json('GET', '/api/users/'.self::TONIO.'/follow-stats', [], $this->auth())
            ->seeJsonEquals(['data' => ['followers' => 0, 'following' => 0, 'is_following' => false]]);
    }

    public function test_follow_stats_with_an_invalid_token_act_as_a_guest(): void
    {
        $this->json('GET', '/api/users/'.self::MARIA.'/follow-stats', [], ['Authorization' => 'Bearer nonsense'])
            ->seeStatusCode(200)
            ->seeJson(['is_following' => null]);
    }
}
