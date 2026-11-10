<?php

namespace Tests;

use App\Models\Rating;
use Firebase\JWT\JWT;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Lumen\Testing\DatabaseTransactions;
use MoviePlatform\Common\Integration\RabbitMqConnection;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;

class RatingApiTest extends TestCase
{
    use DatabaseTransactions;

    private const ME = '0193a7c0-1111-7aaa-8bbb-000000000001';

    private const OTHER = '0193a7c0-2222-7aaa-8bbb-000000000002';

    private const THIRD = '0193a7c0-3333-7aaa-8bbb-000000000003';

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

    private function auth(string $userId = self::ME, int $expiresIn = 1800): array
    {
        $token = JWT::encode(
            ['sub' => $userId, 'role' => 'user', 'name' => 'Tonio Jreige', 'exp' => time() + $expiresIn],
            file_get_contents(base_path('tests/Fixtures/jwt/test-private.pem')),
            'RS256',
        );

        return ['Authorization' => "Bearer $token"];
    }

    private function rating(int $score, string $userId = self::ME, string $movieId = self::MOVIE): Rating
    {
        return Rating::forceCreate(['user_id' => $userId, 'movie_id' => $movieId, 'score' => $score]);
    }

    public function test_the_table_matches_the_erd(): void
    {
        $columns = collect(DB::select(
            "select column_name, data_type, is_nullable from information_schema.columns
             where table_schema = current_schema() and table_name = 'ratings' order by ordinal_position"
        ))->mapWithKeys(fn ($c) => [$c->column_name => $c->data_type.($c->is_nullable === 'YES' ? ' NULL' : '')])->all();

        $this->assertSame([
            'id' => 'uuid',
            'user_id' => 'uuid',
            'movie_id' => 'uuid',
            'score' => 'smallint',
            'created_at' => 'timestamp without time zone NULL',
            'updated_at' => 'timestamp without time zone NULL',
        ], $columns);

        $indexes = collect(DB::select("select indexname from pg_indexes where tablename = 'ratings'"))->pluck('indexname');
        $this->assertContains('ratings_user_id_movie_id_unique', $indexes);
        $this->assertContains('ratings_movie_id_index', $indexes);
    }

    public function test_the_database_refuses_a_score_outside_1_to_5(): void
    {
        foreach ([0, 6] as $score) {
            try {
                DB::transaction(fn () => $this->rating($score));
                $this->fail("Score $score was accepted.");
            } catch (QueryException $e) {
                $this->assertStringContainsString('ratings_score_check', $e->getMessage());
            }
        }
    }

    public function test_rating_needs_a_token(): void
    {
        $this->json('POST', '/api/ratings', ['movie_id' => self::MOVIE, 'score' => 4])
            ->seeStatusCode(401)
            ->seeJsonEquals(['message' => 'Unauthenticated.']);
    }

    public function test_i_can_rate_a_movie(): void
    {
        Carbon::setTestNow('2026-11-03T18:20:00Z');

        $this->json('POST', '/api/ratings', ['movie_id' => self::MOVIE, 'score' => 4], $this->auth())
            ->seeStatusCode(200);

        $rating = Rating::ownedBy(self::ME)->sole();
        $this->seeJsonEquals(['data' => [
            'id' => $rating->id, 'movie_id' => self::MOVIE, 'score' => 4, 'created_at' => '2026-11-03T18:20:00Z',
        ]]);
    }

    public function test_rating_twice_updates_the_same_row_and_publishes_both_times(): void
    {
        $this->json('POST', '/api/ratings', ['movie_id' => self::MOVIE, 'score' => 4], $this->auth())->seeStatusCode(200);
        $id = $this->response->getData(true)['data']['id'];

        $this->json('POST', '/api/ratings', ['movie_id' => self::MOVIE, 'score' => 2], $this->auth())
            ->seeStatusCode(200)
            ->seeJson(['id' => $id, 'score' => 2]);

        $this->assertSame(2, Rating::ownedBy(self::ME)->sole()->score);
        $this->assertCount(2, $this->published);
        $this->assertSame(4, $this->published[0][1]['data']['score']);
        $this->assertSame(2, $this->published[1][1]['data']['score']);
    }

    public function test_rating_added_is_published_with_the_agreed_payload_after_commit(): void
    {
        $this->json('POST', '/api/ratings', ['movie_id' => self::MOVIE, 'score' => 5], $this->auth())->seeStatusCode(200);

        $this->assertCount(1, $this->published);
        [$routingKey, $envelope, $transactionLevel] = $this->published[0];
        $this->assertSame('rating.added', $routingKey);
        $this->assertSame('RatingAdded', $envelope['event']);
        $this->assertSame([
            'rating_id' => Rating::ownedBy(self::ME)->sole()->id,
            'user_id' => self::ME,
            'movie_id' => self::MOVIE,
            'score' => 5,
        ], $envelope['data']);
        $this->assertSame(1, $transactionLevel, 'Only the test transaction may be open when the event is published.');
    }

    public function test_a_score_outside_1_to_5_is_rejected_and_publishes_nothing(): void
    {
        foreach ([0, 6, 3.5, 'four', null] as $score) {
            $this->json('POST', '/api/ratings', ['movie_id' => self::MOVIE, 'score' => $score], $this->auth())
                ->seeStatusCode(422);

            $this->assertArrayHasKey('score', $this->response->getData(true)['errors']);
        }

        $this->assertSame(0, Rating::ownedBy(self::ME)->count());
        $this->assertSame([], $this->published);
    }

    public function test_rating_needs_a_valid_movie_id(): void
    {
        foreach ([['score' => 4], ['movie_id' => 'not-a-uuid', 'score' => 4]] as $body) {
            $this->json('POST', '/api/ratings', $body, $this->auth())->seeStatusCode(422);

            $this->assertArrayHasKey('movie_id', $this->response->getData(true)['errors']);
        }
    }

    public function test_summary_for_a_guest_has_no_my_score(): void
    {
        $this->rating(5);
        $this->rating(4, self::OTHER);
        $this->rating(1, self::THIRD, self::OTHER_MOVIE);

        $this->get('/api/movies/'.self::MOVIE.'/rating-summary')
            ->seeStatusCode(200)
            ->seeJsonEquals(['data' => ['average' => 4.5, 'count' => 2, 'my_score' => null]]);
    }

    public function test_summary_with_a_token_has_my_score(): void
    {
        $this->rating(5);
        $this->rating(4, self::OTHER);

        $this->get('/api/movies/'.self::MOVIE.'/rating-summary', $this->auth())
            ->seeStatusCode(200)
            ->seeJsonEquals(['data' => ['average' => 4.5, 'count' => 2, 'my_score' => 5]]);
    }

    public function test_my_score_is_null_when_i_did_not_rate_the_movie(): void
    {
        $this->rating(4, self::OTHER);

        $this->get('/api/movies/'.self::MOVIE.'/rating-summary', $this->auth())
            ->seeStatusCode(200)
            ->seeJsonEquals(['data' => ['average' => 4, 'count' => 1, 'my_score' => null]]);
    }

    public function test_summary_treats_an_invalid_or_expired_token_as_a_guest(): void
    {
        $this->rating(5);

        foreach ([['Authorization' => 'Bearer not-a-token'], $this->auth(expiresIn: -3600)] as $headers) {
            $this->get('/api/movies/'.self::MOVIE.'/rating-summary', $headers)
                ->seeStatusCode(200)
                ->seeJsonEquals(['data' => ['average' => 5, 'count' => 1, 'my_score' => null]]);
        }
    }

    public function test_summary_of_a_movie_without_ratings(): void
    {
        $this->get('/api/movies/'.self::MOVIE.'/rating-summary')
            ->seeStatusCode(200)
            ->seeJsonEquals(['data' => ['average' => null, 'count' => 0, 'my_score' => null]]);
    }

    public function test_summary_rounds_the_average_to_2_decimals(): void
    {
        $this->rating(5);
        $this->rating(5, self::OTHER);
        $this->rating(4, self::THIRD);

        $this->get('/api/movies/'.self::MOVIE.'/rating-summary')
            ->seeStatusCode(200)
            ->seeJsonEquals(['data' => ['average' => 4.67, 'count' => 3, 'my_score' => null]]);
    }

    public function test_summary_with_a_malformed_id_is_rejected(): void
    {
        $this->get('/api/movies/not-a-uuid/rating-summary')->seeStatusCode(422);

        $this->assertArrayHasKey('movie_id', $this->response->getData(true)['errors']);
    }
}
