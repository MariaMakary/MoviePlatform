<?php

namespace Tests;

use App\Models\Notification;
use Firebase\JWT\JWT;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class NotificationApiTest extends TestCase
{
    private const ME = '0193a7c0-1111-7aaa-8bbb-000000000001';

    private const OTHER = '0193a7c0-2222-7aaa-8bbb-000000000002';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'testing',
            'database.connections.testing' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
            'jwt.public_key_path' => base_path('tests/Fixtures/jwt/test-public.pem'),
        ]);
        DB::purge('testing');

        $this->artisan('migrate');
    }

    private function auth(string $userId = self::ME): array
    {
        $token = JWT::encode(
            ['sub' => $userId, 'role' => 'user', 'name' => 'Joe Rohban', 'exp' => time() + 1800],
            file_get_contents(base_path('tests/Fixtures/jwt/test-private.pem')),
            'RS256',
        );

        return ['Authorization' => "Bearer $token"];
    }

    private function notification(string $userId = self::ME, array $attributes = []): Notification
    {
        return Notification::forceCreate(array_merge([
            'user_id' => $userId,
            'type' => 'new_follower',
            'title' => 'New follower',
            'body' => 'Maria started following you.',
            'data' => ['follower_id' => self::OTHER],
        ], $attributes));
    }

    public function test_the_table_matches_the_erd(): void
    {
        $this->assertSame(
            ['id', 'user_id', 'type', 'title', 'body', 'data', 'read_at', 'created_at', 'updated_at'],
            Schema::getColumnListing('notifications'),
        );

        $indexes = collect(Schema::getIndexes('notifications'))->pluck('columns')->all();
        $this->assertContains(['user_id', 'read_at'], $indexes);
        $this->assertContains(['user_id', 'created_at'], $indexes);
    }

    public function test_every_route_needs_a_token(): void
    {
        $id = $this->notification()->id;

        foreach ([['GET', '/api/notifications'], ['GET', '/api/notifications/unread-count'],
            ['PATCH', "/api/notifications/$id/read"], ['PATCH', '/api/notifications/read-all']] as [$method, $uri]) {
            $this->json($method, $uri)
                ->seeStatusCode(401)
                ->seeJsonEquals(['message' => 'Unauthenticated.']);
        }
    }

    public function test_list_shows_only_my_notifications_newest_first(): void
    {
        $old = $this->notification(attributes: ['created_at' => Carbon::parse('2026-11-01T10:00:00Z')]);
        $new = $this->notification(attributes: ['created_at' => Carbon::parse('2026-11-02T10:00:00Z'), 'read_at' => Carbon::parse('2026-11-02T11:00:00Z')]);
        $this->notification(self::OTHER);

        $this->get('/api/notifications', $this->auth())
            ->seeStatusCode(200)
            ->seeJsonEquals([
                'data' => [
                    [
                        'id' => $new->id, 'type' => 'new_follower', 'title' => 'New follower',
                        'body' => 'Maria started following you.', 'data' => ['follower_id' => self::OTHER],
                        'read_at' => '2026-11-02T11:00:00Z', 'created_at' => '2026-11-02T10:00:00Z',
                    ],
                    [
                        'id' => $old->id, 'type' => 'new_follower', 'title' => 'New follower',
                        'body' => 'Maria started following you.', 'data' => ['follower_id' => self::OTHER],
                        'read_at' => null, 'created_at' => '2026-11-01T10:00:00Z',
                    ],
                ],
                'meta' => ['current_page' => 1, 'last_page' => 1, 'total' => 2],
            ]);
    }

    public function test_list_is_paginated(): void
    {
        foreach (range(1, 5) as $day) {
            $this->notification(attributes: ['title' => "Day $day", 'created_at' => Carbon::parse("2026-11-0{$day}T10:00:00Z")]);
        }

        $this->get('/api/notifications?per_page=2&page=2', $this->auth())
            ->seeStatusCode(200)
            ->seeJson(['meta' => ['current_page' => 2, 'last_page' => 3, 'total' => 5]]);

        $this->assertSame(['Day 3', 'Day 2'], array_column($this->response->getData(true)['data'], 'title'));
    }

    public function test_unread_count_counts_only_my_unread_notifications(): void
    {
        $this->notification();
        $this->notification();
        $this->notification(attributes: ['read_at' => Carbon::now()]);
        $this->notification(self::OTHER);

        $this->get('/api/notifications/unread-count', $this->auth())
            ->seeStatusCode(200)
            ->seeJsonEquals(['data' => ['count' => 2]]);
    }

    public function test_i_can_mark_my_notification_as_read(): void
    {
        Carbon::setTestNow('2026-11-03T18:20:00Z');
        $notification = $this->notification();

        $this->json('PATCH', "/api/notifications/{$notification->id}/read", [], $this->auth())
            ->seeStatusCode(200)
            ->seeJson(['id' => $notification->id, 'read_at' => '2026-11-03T18:20:00Z']);

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_marking_an_already_read_notification_keeps_its_read_at(): void
    {
        $notification = $this->notification(attributes: ['read_at' => Carbon::parse('2026-11-01T09:00:00Z')]);

        $this->json('PATCH', "/api/notifications/{$notification->id}/read", [], $this->auth())
            ->seeStatusCode(200)
            ->seeJson(['read_at' => '2026-11-01T09:00:00Z']);
    }

    public function test_i_cannot_mark_another_users_notification_as_read(): void
    {
        $theirs = $this->notification(self::OTHER);

        $this->json('PATCH', "/api/notifications/{$theirs->id}/read", [], $this->auth())
            ->seeStatusCode(404)
            ->seeJsonEquals(['message' => 'Notification not found.']);

        $this->assertNull($theirs->fresh()->read_at);
    }

    public function test_an_unknown_or_malformed_id_is_not_found(): void
    {
        foreach (['0193a7c0-9999-7aaa-8bbb-000000000009', 'not-a-uuid'] as $id) {
            $this->json('PATCH', "/api/notifications/$id/read", [], $this->auth())
                ->seeStatusCode(404)
                ->seeJsonEquals(['message' => 'Notification not found.']);
        }
    }

    public function test_read_all_marks_only_my_notifications(): void
    {
        $alreadyRead = $this->notification(attributes: ['read_at' => Carbon::parse('2026-11-01T09:00:00Z')]);
        $mine = [$this->notification(), $this->notification()];
        $theirs = $this->notification(self::OTHER);

        $this->json('PATCH', '/api/notifications/read-all', [], $this->auth())->seeStatusCode(204);

        foreach ($mine as $notification) {
            $this->assertNotNull($notification->fresh()->read_at);
        }
        $this->assertEquals(Carbon::parse('2026-11-01T09:00:00Z'), $alreadyRead->fresh()->read_at);
        $this->assertNull($theirs->fresh()->read_at);
    }
}
