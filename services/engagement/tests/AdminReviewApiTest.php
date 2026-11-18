<?php

namespace Tests;

use App\Models\Review;
use Firebase\JWT\JWT;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Lumen\Testing\DatabaseTransactions;
use MoviePlatform\Common\Integration\RabbitMqConnection;
use PhpAmqpLib\Channel\AMQPChannel;

class AdminReviewApiTest extends TestCase
{
    use DatabaseTransactions;

    private const ADMIN = '0193a7c0-9999-7aaa-8bbb-000000000009';

    private const AUTHOR = '0193a7c0-1111-7aaa-8bbb-000000000001';

    private const OTHER = '0193a7c0-2222-7aaa-8bbb-000000000002';

    private const INCEPTION = '0193a7c1-3333-7aaa-8bbb-000000000100';

    private const ARRIVAL = '0193a7c1-3333-7aaa-8bbb-000000000200';

    private const UNKNOWN = '0193a7d1-6666-7aaa-8bbb-000000000999';

    private array $published = [];

    protected function setUp(): void
    {
        parent::setUp();

        config(['jwt.public_key_path' => base_path('tests/Fixtures/jwt/test-public.pem')]);

        $channel = $this->createMock(AMQPChannel::class);
        $channel->method('basic_publish')->willReturnCallback(function ($message, string $exchange, string $routingKey) {
            $this->published[] = $routingKey;
        });
        $rabbitMq = $this->createMock(RabbitMqConnection::class);
        $rabbitMq->method('channel')->willReturn($channel);
        $rabbitMq->method('exchange')->willReturn('movieplatform.events');
        $this->app->instance(RabbitMqConnection::class, $rabbitMq);

        // Inside the test transaction: local data never changes the lists.
        DB::table('reviews')->delete();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function token(string $role = 'admin', string $userId = self::ADMIN): array
    {
        $this->app['auth']->forgetGuards();

        $token = JWT::encode(
            ['sub' => $userId, 'role' => $role, 'name' => 'Admin', 'exp' => time() + 1800],
            file_get_contents(base_path('tests/Fixtures/jwt/test-private.pem')),
            'RS256',
        );

        return ['Authorization' => "Bearer $token"];
    }

    private function review(array $attributes = []): Review
    {
        return Review::forceCreate(array_merge([
            'user_id' => self::AUTHOR,
            'movie_id' => self::INCEPTION,
            'body' => 'Loved the ending, the soundtrack is amazing.',
        ], $attributes));
    }

    private function body(): array
    {
        return json_decode($this->response->getContent(), true);
    }

    private function adminList(string $query = ''): array
    {
        $this->json('GET', "/api/admin/reviews$query", [], $this->token());
        $this->seeStatusCode(200);

        return $this->body();
    }

    private function ids(array $body): array
    {
        return array_column($body['data'], 'id');
    }

    private function publicIds(string $path): array
    {
        $this->app['auth']->forgetGuards();
        $this->json('GET', $path);
        $this->seeStatusCode(200);

        return $this->ids($this->body());
    }

    // --- Who ---

    public function test_a_normal_user_gets_403_on_every_moderation_endpoint(): void
    {
        $review = $this->review();
        $user = $this->token('user', self::AUTHOR);

        foreach ([
            ['GET', '/api/admin/reviews'],
            ['PATCH', "/api/admin/reviews/{$review->id}/hide"],
            ['PATCH', "/api/admin/reviews/{$review->id}/unhide"],
            ['DELETE', "/api/admin/reviews/{$review->id}"],
        ] as [$method, $uri]) {
            $this->json($method, $uri, [], $user);
            $this->seeStatusCode(403);
        }

        $this->assertFalse($review->fresh()->is_hidden);
        $this->assertNotNull($review->fresh(), 'Even the author cannot use the admin delete.');
    }

    public function test_a_guest_gets_401(): void
    {
        $review = $this->review();

        $this->json('GET', '/api/admin/reviews')->seeStatusCode(401);
        $this->json('PATCH', "/api/admin/reviews/{$review->id}/hide")->seeStatusCode(401);
        $this->json('DELETE', "/api/admin/reviews/{$review->id}")->seeStatusCode(401);
    }

    // --- GET /api/admin/reviews ---

    public function test_the_list_has_every_review_newest_first_with_the_moderation_flags(): void
    {
        Carbon::setTestNow('2026-11-10T10:00:00Z');
        $old = $this->review();
        Carbon::setTestNow('2026-11-11T10:00:00Z');
        $hidden = $this->review(['user_id' => self::OTHER, 'is_hidden' => true]);
        Carbon::setTestNow('2026-11-12T10:00:00Z');
        $blocked = $this->review(['movie_id' => self::ARRIVAL, 'author_blocked' => true, 'sentiment_label' => 'negative', 'sentiment_score' => 0.91]);

        $body = $this->adminList();

        $this->assertSame([$blocked->id, $hidden->id, $old->id], $this->ids($body));
        $this->assertSame(['current_page' => 1, 'last_page' => 1, 'total' => 3], $body['meta']);
        $this->assertSame([
            'id' => $blocked->id,
            'user_id' => self::AUTHOR,
            'movie_id' => self::ARRIVAL,
            'body' => 'Loved the ending, the soundtrack is amazing.',
            'author_score' => null,
            'sentiment_label' => 'negative',
            'sentiment_score' => 0.91,
            'created_at' => '2026-11-12T10:00:00Z',
            'updated_at' => '2026-11-12T10:00:00Z',
            'is_hidden' => false,
            'author_blocked' => true,
        ], $body['data'][0]);
        $this->assertTrue($body['data'][1]['is_hidden']);
        $this->assertFalse($body['data'][1]['author_blocked']);
    }

    public function test_the_list_is_paginated(): void
    {
        foreach (range(1, 3) as $n) {
            $this->review(['user_id' => sprintf('0193a7c0-5555-7aaa-8bbb-%012d', $n)]);
        }

        $body = $this->adminList('?per_page=2&page=2');

        $this->assertCount(1, $body['data']);
        $this->assertSame(['current_page' => 2, 'last_page' => 2, 'total' => 3], $body['meta']);
    }

    public function test_it_filters_by_sentiment_movie_and_hidden(): void
    {
        $negative = $this->review(['sentiment_label' => 'negative', 'sentiment_score' => 0.8]);
        $positive = $this->review(['user_id' => self::OTHER, 'sentiment_label' => 'positive', 'sentiment_score' => 0.9]);
        $arrivalHidden = $this->review(['movie_id' => self::ARRIVAL, 'is_hidden' => true]);

        $this->assertSame([$negative->id], $this->ids($this->adminList('?sentiment=negative')));
        $this->assertEqualsCanonicalizing([$negative->id, $positive->id], $this->ids($this->adminList('?movie_id='.self::INCEPTION)));
        $this->assertSame([$arrivalHidden->id], $this->ids($this->adminList('?hidden=true')));
        $this->assertSame([$arrivalHidden->id], $this->ids($this->adminList('?hidden=1')));
        $this->assertEqualsCanonicalizing([$negative->id, $positive->id], $this->ids($this->adminList('?hidden=false')));
        $this->assertSame([$positive->id], $this->ids($this->adminList('?sentiment=positive&movie_id='.self::INCEPTION.'&hidden=false')));
    }

    public function test_empty_filters_are_ignored(): void
    {
        $this->review();
        $this->review(['user_id' => self::OTHER, 'is_hidden' => true]);

        $this->assertCount(2, $this->adminList('?sentiment=&movie_id=&hidden=')['data']);
    }

    public function test_invalid_filters_give_422(): void
    {
        $this->json('GET', '/api/admin/reviews?sentiment=angry&movie_id=42&hidden=maybe', [], $this->token());

        $this->seeStatusCode(422);
        $this->assertEqualsCanonicalizing(['sentiment', 'movie_id', 'hidden'], array_keys($this->body()['errors']));
    }

    // --- hide / unhide ---

    public function test_a_hidden_review_leaves_the_public_lists_and_comes_back_after_unhide(): void
    {
        $review = $this->review();
        $movieReviews = '/api/movies/'.self::INCEPTION.'/reviews';
        $userReviews = '/api/users/'.self::AUTHOR.'/reviews';

        $this->json('PATCH', "/api/admin/reviews/{$review->id}/hide", [], $this->token());
        $this->seeStatusCode(200);
        $this->assertSame($review->id, $this->body()['data']['id']);
        $this->assertTrue($this->body()['data']['is_hidden']);
        $this->assertSame([], $this->publicIds($movieReviews));
        $this->assertSame([], $this->publicIds($userReviews));

        $this->json('PATCH', "/api/admin/reviews/{$review->id}/unhide", [], $this->token());
        $this->seeStatusCode(200);
        $this->assertFalse($this->body()['data']['is_hidden']);
        $this->assertSame([$review->id], $this->publicIds($movieReviews));
        $this->assertSame([$review->id], $this->publicIds($userReviews));
    }

    public function test_hiding_changes_only_is_hidden(): void
    {
        Carbon::setTestNow('2026-11-10T10:00:00Z');
        $review = $this->review(['author_blocked' => true, 'sentiment_label' => 'neutral', 'sentiment_score' => 0.5]);
        Carbon::setTestNow('2026-11-12T10:00:00Z');

        $this->json('PATCH', "/api/admin/reviews/{$review->id}/hide", [], $this->token())->seeStatusCode(200);
        $this->json('PATCH', "/api/admin/reviews/{$review->id}/hide", [], $this->token())->seeStatusCode(200);

        $fresh = $review->fresh();
        $this->assertTrue($fresh->is_hidden);
        $this->assertTrue($fresh->author_blocked, 'author_blocked belongs to user.blocked / user.unblocked.');
        $this->assertSame('2026-11-10T10:00:00Z', $fresh->updated_at->toIso8601ZuluString(), 'Moderation is not an edit by the author.');
        $this->assertSame($review->body, $fresh->body);
        $this->assertSame([], $this->published);
    }

    public function test_unhide_keeps_a_blocked_authors_review_out_of_the_public_lists(): void
    {
        $review = $this->review(['is_hidden' => true, 'author_blocked' => true]);

        $this->json('PATCH', "/api/admin/reviews/{$review->id}/unhide", [], $this->token())->seeStatusCode(200);

        $this->assertTrue($this->body()['data']['author_blocked']);
        $this->assertSame([], $this->publicIds('/api/movies/'.self::INCEPTION.'/reviews'));
    }

    public function test_hide_and_unhide_of_an_unknown_review_give_404(): void
    {
        foreach (['hide', 'unhide'] as $action) {
            $this->json('PATCH', '/api/admin/reviews/'.self::UNKNOWN."/$action", [], $this->token());
            $this->seeStatusCode(404);
            $this->assertSame('Review not found.', $this->body()['message']);

            $this->json('PATCH', "/api/admin/reviews/not-a-uuid/$action", [], $this->token());
            $this->seeStatusCode(404);
        }
    }

    // --- DELETE ---

    public function test_an_admin_deletes_any_review(): void
    {
        $review = $this->review(['is_hidden' => true]);

        $this->json('DELETE', "/api/admin/reviews/{$review->id}", [], $this->token());

        $this->seeStatusCode(204);
        $this->assertSame('', $this->response->getContent());
        $this->assertNull($review->fresh());
    }

    public function test_deleting_an_unknown_review_gives_404(): void
    {
        $this->json('DELETE', '/api/admin/reviews/'.self::UNKNOWN, [], $this->token())->seeStatusCode(404);
        $this->json('DELETE', '/api/admin/reviews/not-a-uuid', [], $this->token())->seeStatusCode(404);
    }
}
