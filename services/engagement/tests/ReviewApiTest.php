<?php

namespace Tests;

use App\Models\Rating;
use App\Models\Review;
use Firebase\JWT\JWT;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Lumen\Testing\DatabaseTransactions;
use MoviePlatform\Common\Integration\RabbitMqConnection;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;

class ReviewApiTest extends TestCase
{
    use DatabaseTransactions;

    private const ME = '0193a7c0-1111-7aaa-8bbb-000000000001';

    private const OTHER = '0193a7c0-2222-7aaa-8bbb-000000000002';

    private const MOVIE = '0193a7c1-3333-7aaa-8bbb-000000000100';

    private const OTHER_MOVIE = '0193a7c1-3333-7aaa-8bbb-000000000200';

    private const BODY = 'Loved the ending, the soundtrack is amazing.';

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

    private function review(string $userId = self::ME, string $movieId = self::MOVIE, array $attributes = []): Review
    {
        return Review::forceCreate(array_merge(['user_id' => $userId, 'movie_id' => $movieId, 'body' => self::BODY], $attributes));
    }

    private function body(): array
    {
        return json_decode($this->response->getContent(), true);
    }

    public function test_the_table_matches_the_erd(): void
    {
        $columns = collect(DB::select(
            "select column_name, data_type, character_maximum_length, numeric_precision, numeric_scale, is_nullable, column_default
             from information_schema.columns where table_schema = current_schema() and table_name = 'reviews' order by ordinal_position"
        ))->mapWithKeys(fn ($c) => [$c->column_name => implode(' ', array_filter([
            $c->data_type,
            $c->character_maximum_length ? "({$c->character_maximum_length})" : null,
            $c->data_type === 'numeric' ? "({$c->numeric_precision},{$c->numeric_scale})" : null,
            $c->is_nullable === 'YES' ? 'NULL' : null,
            $c->column_default === 'false' ? 'default false' : null,
        ]))])->all();

        $this->assertSame([
            'id' => 'uuid',
            'user_id' => 'uuid',
            'movie_id' => 'uuid',
            'body' => 'text',
            'sentiment_label' => 'character varying (20) NULL',
            'sentiment_score' => 'numeric (5,4) NULL',
            'is_hidden' => 'boolean default false',
            'author_blocked' => 'boolean default false',
            'created_at' => 'timestamp without time zone NULL',
            'updated_at' => 'timestamp without time zone NULL',
        ], $columns);

        $indexes = collect(DB::select("select indexname from pg_indexes where tablename = 'reviews'"))->pluck('indexname');
        $this->assertContains('reviews_user_id_movie_id_unique', $indexes);
        $this->assertContains('reviews_movie_id_created_at_index', $indexes);
    }

    public function test_the_database_refuses_an_unknown_sentiment_label(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('reviews_sentiment_label_check');

        // Straight to the table: the model's enum cast would already refuse it in PHP.
        DB::table('reviews')->insert([
            'id' => '0193a7d1-6666-7aaa-8bbb-000000000999', 'user_id' => self::ME, 'movie_id' => self::MOVIE,
            'body' => self::BODY, 'sentiment_label' => 'angry',
        ]);
    }

    public function test_writing_needs_a_token(): void
    {
        $id = $this->review()->id;

        foreach ([['POST', '/api/reviews'], ['PUT', "/api/reviews/$id"], ['DELETE', "/api/reviews/$id"]] as [$method, $uri]) {
            $this->json($method, $uri, ['movie_id' => self::MOVIE, 'body' => self::BODY])
                ->seeStatusCode(401)
                ->seeJsonEquals(['message' => 'Unauthenticated.']);
        }
    }

    public function test_i_can_write_a_review(): void
    {
        Carbon::setTestNow('2026-11-03T18:20:00Z');
        Rating::forceCreate(['user_id' => self::ME, 'movie_id' => self::MOVIE, 'score' => 5]);

        $this->json('POST', '/api/reviews', ['movie_id' => self::MOVIE, 'body' => self::BODY], $this->auth())
            ->seeStatusCode(201);

        $review = Review::where('user_id', self::ME)->sole();
        $this->seeJsonEquals(['data' => [
            'id' => $review->id, 'user_id' => self::ME, 'movie_id' => self::MOVIE, 'body' => self::BODY,
            'author_score' => 5, 'sentiment_label' => null, 'sentiment_score' => null,
            'created_at' => '2026-11-03T18:20:00Z', 'updated_at' => '2026-11-03T18:20:00Z',
        ]]);
    }

    public function test_review_created_is_published_with_the_agreed_payload_after_commit(): void
    {
        $this->json('POST', '/api/reviews', ['movie_id' => self::MOVIE, 'body' => self::BODY], $this->auth())->seeStatusCode(201);

        $this->assertCount(1, $this->published);
        [$routingKey, $envelope, $transactionLevel] = $this->published[0];
        $this->assertSame('review.created', $routingKey);
        $this->assertSame('ReviewCreated', $envelope['event']);
        $this->assertSame([
            'review_id' => Review::where('user_id', self::ME)->value('id'),
            'user_id' => self::ME,
            'movie_id' => self::MOVIE,
            'body' => self::BODY,
        ], $envelope['data']);
        $this->assertSame(1, $transactionLevel, 'Only the test transaction may be open when the event is published.');
    }

    public function test_a_second_review_of_the_same_movie_gets_422(): void
    {
        $this->review();

        $this->json('POST', '/api/reviews', ['movie_id' => self::MOVIE, 'body' => 'Another review of it.'], $this->auth())
            ->seeStatusCode(422)
            ->seeJsonEquals(['message' => 'The given data was invalid.', 'errors' => ['movie_id' => ['You already reviewed this movie.']]]);

        $this->assertSame(1, Review::where('user_id', self::ME)->count());
        $this->assertSame([], $this->published);
    }

    public function test_the_body_is_validated(): void
    {
        foreach (['Too short', str_repeat('a', 2001), str_repeat(' ', 20), null] as $body) {
            $this->json('POST', '/api/reviews', ['movie_id' => self::MOVIE, 'body' => $body], $this->auth())->seeStatusCode(422);

            $this->assertArrayHasKey('body', $this->body()['errors']);
        }

        $this->json('POST', '/api/reviews', ['movie_id' => 'not-a-uuid', 'body' => self::BODY], $this->auth())->seeStatusCode(422);
        $this->assertArrayHasKey('movie_id', $this->body()['errors']);

        $this->json('POST', '/api/reviews', ['movie_id' => self::MOVIE, 'body' => str_repeat('a', 2000)], $this->auth())->seeStatusCode(201);
    }

    public function test_the_author_can_edit_and_review_updated_is_published(): void
    {
        $review = $this->review();

        $this->json('PUT', "/api/reviews/{$review->id}", ['body' => 'Even better the second time.'], $this->auth())
            ->seeStatusCode(200)
            ->seeJson(['id' => $review->id, 'body' => 'Even better the second time.']);

        $this->assertSame('Even better the second time.', $review->fresh()->body);
        $this->assertCount(1, $this->published);
        $this->assertSame('review.updated', $this->published[0][0]);
        $this->assertSame('ReviewUpdated', $this->published[0][1]['event']);
        $this->assertSame([
            'review_id' => $review->id, 'user_id' => self::ME, 'movie_id' => self::MOVIE, 'body' => 'Even better the second time.',
        ], $this->published[0][1]['data']);
    }

    public function test_saving_the_same_text_publishes_nothing(): void
    {
        $review = $this->review();

        $this->json('PUT', "/api/reviews/{$review->id}", ['body' => self::BODY], $this->auth())->seeStatusCode(200);

        $this->assertSame([], $this->published);
    }

    public function test_only_the_author_can_edit_or_delete(): void
    {
        $theirs = $this->review(self::OTHER);

        $this->json('PUT', "/api/reviews/{$theirs->id}", ['body' => 'I rewrote your review.'], $this->auth())
            ->seeStatusCode(403);
        $this->json('DELETE', "/api/reviews/{$theirs->id}", [], $this->auth())
            ->seeStatusCode(403);

        $this->assertSame(self::BODY, $theirs->fresh()->body);
        $this->assertSame([], $this->published);
    }

    public function test_the_author_can_delete(): void
    {
        $review = $this->review();

        $this->json('DELETE', "/api/reviews/{$review->id}", [], $this->auth())->seeStatusCode(204);

        $this->assertNull(Review::find($review->id));
    }

    public function test_an_unknown_or_malformed_review_id_is_not_found(): void
    {
        foreach (['0193a7d1-9999-7aaa-8bbb-000000000999', 'not-a-uuid'] as $id) {
            $this->json('PUT', "/api/reviews/$id", ['body' => self::BODY], $this->auth())
                ->seeStatusCode(404)
                ->seeJsonEquals(['message' => 'Review not found.']);
        }
    }

    public function test_movie_reviews_are_newest_first_with_the_author_score(): void
    {
        $old = $this->review(self::ME, attributes: ['created_at' => Carbon::parse('2026-11-01T10:00:00Z')]);
        $new = $this->review(self::OTHER, attributes: ['created_at' => Carbon::parse('2026-11-02T10:00:00Z')]);
        $this->review(self::ME, self::OTHER_MOVIE);
        Rating::forceCreate(['user_id' => self::ME, 'movie_id' => self::MOVIE, 'score' => 4]);
        Rating::forceCreate(['user_id' => self::ME, 'movie_id' => self::OTHER_MOVIE, 'score' => 1]);

        $this->get('/api/movies/'.self::MOVIE.'/reviews')->seeStatusCode(200);

        $body = $this->body();
        $this->assertSame([$new->id, $old->id], array_column($body['data'], 'id'));
        $this->assertSame([null, 4], array_column($body['data'], 'author_score'));
        $this->assertSame(['current_page' => 1, 'last_page' => 1, 'total' => 2], $body['meta']);
    }

    public function test_hidden_and_author_blocked_reviews_never_appear_in_the_lists(): void
    {
        $visible = $this->review(self::ME);
        $this->review(self::OTHER, attributes: ['is_hidden' => true]);
        $this->review('0193a7c0-3333-7aaa-8bbb-000000000003', attributes: ['author_blocked' => true]);
        $this->review(self::OTHER, self::OTHER_MOVIE, ['author_blocked' => true]);

        $this->get('/api/movies/'.self::MOVIE.'/reviews');
        $this->assertSame([$visible->id], array_column($this->body()['data'], 'id'));

        $this->get('/api/users/'.self::OTHER.'/reviews');
        $this->assertSame([], $this->body()['data']);
    }

    public function test_user_reviews_show_only_that_users_reviews(): void
    {
        $mine = [$this->review(self::ME), $this->review(self::ME, self::OTHER_MOVIE)];
        $this->review(self::OTHER);

        $this->get('/api/users/'.self::ME.'/reviews')->seeStatusCode(200);

        $this->assertEqualsCanonicalizing(array_column($mine, 'id'), array_column($this->body()['data'], 'id'));
    }

    public function test_the_lists_are_paginated(): void
    {
        foreach (range(1, 5) as $day) {
            $this->review("0193a7c0-4444-7aaa-8bbb-00000000000$day", attributes: ['created_at' => Carbon::parse("2026-11-0{$day}T10:00:00Z")]);
        }

        $this->get('/api/movies/'.self::MOVIE.'/reviews?per_page=2&page=2')->seeStatusCode(200);

        $this->assertSame(['current_page' => 2, 'last_page' => 3, 'total' => 5], $this->body()['meta']);
        $this->assertSame(['0193a7c0-4444-7aaa-8bbb-000000000003', '0193a7c0-4444-7aaa-8bbb-000000000002'], array_column($this->body()['data'], 'user_id'));
    }

    public function test_a_malformed_movie_or_user_id_gets_422(): void
    {
        $this->get('/api/movies/not-a-uuid/reviews')->seeStatusCode(422);
        $this->assertArrayHasKey('movie_id', $this->body()['errors']);

        $this->get('/api/users/not-a-uuid/reviews')->seeStatusCode(422);
        $this->assertArrayHasKey('user_id', $this->body()['errors']);
    }
}
