<?php

namespace Tests;

use App\Models\PasswordResetToken;
use App\Models\User;
use App\Models\UserGenre;
use App\Services\ProfileService;
use App\Services\TokenService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Lumen\Testing\DatabaseTransactions;
use MoviePlatform\Common\Integration\RabbitMqConnection;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;

class AdminUserApiTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;

    private User $maria;

    /** [routing key, envelope, transaction level when published] */
    private array $published = [];

    private array $files = [];

    protected function setUp(): void
    {
        parent::setUp();

        $fixtures = base_path('tests/Fixtures/jwt');
        config([
            'jwt.private_key' => file_get_contents($fixtures.'/test-private.pem'),
            'jwt.public_key_path' => $fixtures.'/test-public.pem',
            'app.url' => 'http://localhost:8001',
        ]);

        $channel = $this->createMock(AMQPChannel::class);
        $channel->method('basic_publish')->willReturnCallback(function (AMQPMessage $message, string $exchange, string $routingKey) {
            $this->published[] = [$routingKey, json_decode($message->getBody(), true), DB::transactionLevel()];
        });
        $rabbitMq = $this->createMock(RabbitMqConnection::class);
        $rabbitMq->method('channel')->willReturn($channel);
        $rabbitMq->method('exchange')->willReturn('movieplatform.events');
        $this->app->instance(RabbitMqConnection::class, $rabbitMq);

        // Inside the test transaction: local accounts never change the lists.
        DB::table('users')->delete();

        Carbon::setTestNow('2026-11-10T10:00:00Z');
        $this->admin = User::factory()->admin()->create(['name' => 'Kaia Admin', 'email' => 'admin@kaia.com']);
        Carbon::setTestNow('2026-11-12T10:00:00Z');
        $this->maria = User::factory()->create(['name' => 'Maria Makary', 'email' => 'maria@example.com']);
        Carbon::setTestNow();
    }

    protected function tearDown(): void
    {
        foreach ($this->files as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function as(User $user): array
    {
        $this->app['auth']->forgetGuards();

        return ['Authorization' => 'Bearer '.app(TokenService::class)->issue($user)->token];
    }

    private function body(): array
    {
        return json_decode($this->response->getContent(), true);
    }

    private function list(string $query = ''): array
    {
        $this->json('GET', "/api/admin/users$query", [], $this->as($this->admin))->seeStatusCode(200);

        return $this->body();
    }

    private function routingKeys(): array
    {
        return array_column($this->published, 0);
    }

    // --- Who ---

    public function test_a_normal_user_gets_403_on_every_endpoint(): void
    {
        $other = User::factory()->create();
        $headers = $this->as($this->maria);

        foreach ([
            ['GET', '/api/admin/users'],
            ['PATCH', "/api/admin/users/{$other->id}/block"],
            ['PATCH', "/api/admin/users/{$other->id}/unblock"],
            ['DELETE', "/api/admin/users/{$other->id}"],
        ] as [$method, $uri]) {
            $this->json($method, $uri, [], $headers)->seeStatusCode(403);
        }

        $this->assertFalse($other->fresh()->is_blocked);
        $this->assertSame([], $this->published);
    }

    public function test_a_guest_gets_401(): void
    {
        $this->json('GET', '/api/admin/users')->seeStatusCode(401);
        $this->json('PATCH', "/api/admin/users/{$this->maria->id}/block")->seeStatusCode(401);
        $this->json('DELETE', "/api/admin/users/{$this->maria->id}")->seeStatusCode(401);
    }

    // --- GET /api/admin/users ---

    public function test_the_list_has_every_user_newest_first_with_email_role_and_status(): void
    {
        Carbon::setTestNow('2026-11-14T10:00:00Z');
        $blocked = User::factory()->blocked()->create(['name' => 'Joe Rohban', 'email' => 'joe@example.com', 'avatar' => 'https://example.com/joe.png']);
        Carbon::setTestNow();

        $body = $this->list();

        $this->assertSame([$blocked->id, $this->maria->id, $this->admin->id], array_column($body['data'], 'id'));
        $this->assertSame(['current_page' => 1, 'last_page' => 1, 'total' => 3], $body['meta']);
        $this->assertSame([
            'id' => $blocked->id,
            'name' => 'Joe Rohban',
            'email' => 'joe@example.com',
            'avatar' => 'https://example.com/joe.png',
            'role' => 'user',
            'is_blocked' => true,
            'created_at' => '2026-11-14T10:00:00Z',
        ], $body['data'][0]);
        $this->assertSame('admin', $body['data'][2]['role']);
        $this->assertArrayNotHasKey('password', $body['data'][0]);
    }

    public function test_it_searches_name_or_email_in_any_case(): void
    {
        User::factory()->create(['name' => 'Tonio Jreige', 'email' => 'tonio@school.edu']);

        $this->assertSame(['Maria Makary'], array_column($this->list('?q=MAKA')['data'], 'name'));
        $this->assertSame(['Tonio Jreige'], array_column($this->list('?q=school.EDU')['data'], 'name'));
        $this->assertSame([], $this->list('?q=%25')['data'], '% is a plain character.');
        $this->assertCount(3, $this->list('?q=')['data'], 'An empty search lists everyone.');
    }

    public function test_the_list_is_paginated(): void
    {
        User::factory()->count(3)->create();

        $body = $this->list('?per_page=2&page=3');

        $this->assertCount(1, $body['data']);
        $this->assertSame(['current_page' => 3, 'last_page' => 3, 'total' => 5], $body['meta']);
    }

    public function test_a_search_longer_than_100_characters_gives_422(): void
    {
        $this->json('GET', '/api/admin/users?q='.str_repeat('a', 101), [], $this->as($this->admin));

        $this->seeStatusCode(422);
        $this->assertArrayHasKey('q', $this->body()['errors']);
    }

    // --- block / unblock ---

    public function test_block_sets_is_blocked_and_publishes_user_blocked_after_the_commit(): void
    {
        $this->json('PATCH', "/api/admin/users/{$this->maria->id}/block", [], $this->as($this->admin));

        $this->seeStatusCode(200);
        $this->assertSame($this->maria->id, $this->body()['data']['id']);
        $this->assertTrue($this->body()['data']['is_blocked']);
        $this->assertTrue($this->maria->fresh()->is_blocked);

        $this->assertCount(1, $this->published);
        [$routingKey, $envelope, $level] = $this->published[0];
        $this->assertSame('user.blocked', $routingKey);
        $this->assertSame('UserBlocked', $envelope['event']);
        $this->assertTrue(Str::isUuid($envelope['id']));
        $this->assertSame(['user_id' => $this->maria->id], $envelope['data']);
        $this->assertSame(1, $level, 'Published after the commit, outside the service transaction.');
    }

    public function test_a_blocked_user_cannot_log_in_or_refresh_and_can_again_after_unblock(): void
    {
        $token = app(TokenService::class)->issue($this->maria)->token;

        $this->json('PATCH', "/api/admin/users/{$this->maria->id}/block", [], $this->as($this->admin))->seeStatusCode(200);

        $this->app['auth']->forgetGuards();
        $this->json('POST', '/api/login', ['email' => 'maria@example.com', 'password' => 'password'])->seeStatusCode(403);
        $this->assertSame('Account blocked.', $this->body()['message']);
        $this->app['auth']->forgetGuards();
        $this->json('POST', '/api/token/refresh', [], ['Authorization' => "Bearer $token"])->seeStatusCode(403);

        $this->json('PATCH', "/api/admin/users/{$this->maria->id}/unblock", [], $this->as($this->admin))->seeStatusCode(200);
        $this->assertFalse($this->body()['data']['is_blocked']);

        $this->app['auth']->forgetGuards();
        $this->json('POST', '/api/login', ['email' => 'maria@example.com', 'password' => 'password'])->seeStatusCode(200);
        $this->assertSame(['user.blocked', 'user.unblocked'], $this->routingKeys());
        $this->assertSame(['user_id' => $this->maria->id], $this->published[1][1]['data']);
        $this->assertSame('UserUnblocked', $this->published[1][1]['event']);
    }

    public function test_blocking_twice_or_unblocking_an_active_user_publishes_nothing_more(): void
    {
        $this->json('PATCH', "/api/admin/users/{$this->maria->id}/unblock", [], $this->as($this->admin))->seeStatusCode(200);
        $this->json('PATCH', "/api/admin/users/{$this->maria->id}/block", [], $this->as($this->admin))->seeStatusCode(200);
        $this->json('PATCH', "/api/admin/users/{$this->maria->id}/block", [], $this->as($this->admin))->seeStatusCode(200);

        $this->assertSame(['user.blocked'], $this->routingKeys());
    }

    public function test_an_admin_cannot_block_or_delete_themselves(): void
    {
        $this->json('PATCH', "/api/admin/users/{$this->admin->id}/block", [], $this->as($this->admin));
        $this->seeStatusCode(422);
        $this->assertSame('You cannot block your own account.', $this->body()['message']);

        $this->json('DELETE', '/api/admin/users/'.strtoupper($this->admin->id), [], $this->as($this->admin));
        $this->seeStatusCode(422);
        $this->assertSame('You cannot delete your own account.', $this->body()['message']);

        $this->assertFalse($this->admin->fresh()->is_blocked);
        $this->assertSame([], $this->published);
    }

    public function test_unknown_or_malformed_ids_give_404(): void
    {
        $unknown = (string) Str::uuid();

        foreach ([['PATCH', "/$unknown/block"], ['PATCH', "/$unknown/unblock"], ['DELETE', "/$unknown"]] as [$method, $path]) {
            $this->json($method, "/api/admin/users$path", [], $this->as($this->admin))->seeStatusCode(404);
            $this->assertSame('User not found.', $this->body()['message']);
        }

        $this->json('PATCH', '/api/admin/users/not-a-uuid/block', [], $this->as($this->admin))->seeStatusCode(404);
        $this->assertSame([], $this->published);
    }

    // --- DELETE ---

    public function test_delete_removes_the_user_and_their_data_and_publishes_user_deleted(): void
    {
        $avatar = ProfileService::avatarDirectory().'/'.Str::uuid().'.png';
        @mkdir(dirname($avatar), 0777, true);
        file_put_contents($avatar, 'png');
        $this->files[] = $avatar;
        $this->maria->forceFill(['avatar' => 'http://localhost:8001/avatars/'.basename($avatar)])->save();
        $this->maria->genres()->create(['genre_id' => (string) Str::uuid()]);
        PasswordResetToken::create(['email' => 'maria@example.com', 'token' => 'hashed', 'created_at' => Carbon::now()]);

        $this->json('DELETE', "/api/admin/users/{$this->maria->id}", [], $this->as($this->admin));

        $this->seeStatusCode(204);
        $this->assertSame('', $this->response->getContent());
        $this->assertNull(User::find($this->maria->id));
        $this->assertSame(0, UserGenre::where('user_id', $this->maria->id)->count());
        $this->assertNull(PasswordResetToken::find('maria@example.com'));
        $this->assertFileDoesNotExist($avatar);

        $this->assertCount(1, $this->published);
        [$routingKey, $envelope, $level] = $this->published[0];
        $this->assertSame('user.deleted', $routingKey);
        $this->assertSame('UserDeleted', $envelope['event']);
        $this->assertSame(['user_id' => $this->maria->id], $envelope['data']);
        $this->assertSame(1, $level);

        $this->json('GET', "/api/users/{$this->maria->id}")->seeStatusCode(404);
    }

    public function test_delete_keeps_an_avatar_that_is_not_an_uploaded_file(): void
    {
        $outside = ProfileService::avatarDirectory().'/../keep-'.Str::uuid().'.png';
        file_put_contents($outside, 'png');
        $this->files[] = $outside;
        $this->maria->forceFill(['avatar' => 'https://lh3.googleusercontent.com/'.basename($outside)])->save();

        $this->json('DELETE', "/api/admin/users/{$this->maria->id}", [], $this->as($this->admin))->seeStatusCode(204);

        $this->assertFileExists($outside);
    }

    public function test_an_admin_can_block_or_delete_another_admin(): void
    {
        $other = User::factory()->admin()->create();

        $this->json('PATCH', "/api/admin/users/{$other->id}/block", [], $this->as($this->admin))->seeStatusCode(200);
        $this->json('DELETE', "/api/admin/users/{$other->id}", [], $this->as($this->admin))->seeStatusCode(204);

        $this->assertSame(['user.blocked', 'user.deleted'], $this->routingKeys());
    }
}
