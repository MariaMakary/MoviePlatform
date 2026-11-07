<?php

namespace Tests;

use App\Integration\Handlers\AnalyzeReviewHandler;
use App\Models\SentimentResult;
use Firebase\JWT\JWT;
use Illuminate\Database\DatabaseTransactionsManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Lumen\Testing\DatabaseTransactions;
use MoviePlatform\Common\Integration\EventConsumer;
use MoviePlatform\Common\Integration\RabbitMqConnection;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;

class SentimentTest extends TestCase
{
    use DatabaseTransactions;

    private const REVIEW = '0193a7d1-6666-7aaa-8bbb-000000000400';

    private array $published = [];

    private bool $azureDown = false;

    private array $scores = ['positive' => 0.94, 'neutral' => 0.05, 'negative' => 0.01];

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'jwt.public_key_path' => base_path('tests/Fixtures/jwt/test-public.pem'),
            'services.azure_language.key' => 'test-key',
            'services.azure_language.endpoint' => 'https://kaia-test.cognitiveservices.azure.com',
        ]);

        DB::connection()->setTransactionManager(new class extends DatabaseTransactionsManager
        {
            public function afterCommitCallbacksShouldBeExecuted($level)
            {
                return $level <= 1;
            }
        });

        $channel = $this->createMock(AMQPChannel::class);
        $channel->method('basic_publish')->willReturnCallback(function (AMQPMessage $message, string $exchange, string $routingKey) {
            $this->published[] = [$routingKey, json_decode($message->getBody(), true)['data'] ?? null, DB::transactionLevel()];
        });
        $rabbitMq = $this->createMock(RabbitMqConnection::class);
        $rabbitMq->method('channel')->willReturn($channel);
        $rabbitMq->method('exchange')->willReturn('movieplatform.events');
        $this->app->instance(RabbitMqConnection::class, $rabbitMq);

        Http::fake(['kaia-test.cognitiveservices.azure.com/*' => function () {
            if ($this->azureDown) {
                return Http::response(['error' => ['code' => '429', 'message' => 'Rate limit is exceeded.']], 429);
            }

            return Http::response(['kind' => 'SentimentAnalysisResults', 'results' => [
                'documents' => [['id' => '1', 'sentiment' => 'positive', 'confidenceScores' => $this->scores, 'sentences' => [], 'warnings' => []]],
                'errors' => [],
                'modelVersion' => '2025-01-01',
            ]]);
        }]);
    }

    private function deliver(string $event, string $body, ?string $id = null): void
    {
        $message = new AMQPMessage(json_encode([
            'event' => $event,
            'id' => $id ?? (string) Str::uuid(),
            'occurred_at' => '2026-11-04T10:00:00Z',
            'data' => ['review_id' => self::REVIEW, 'user_id' => '0193a7c0-1111-7aaa-8bbb-000000000001',
                'movie_id' => '0193a7c1-3333-7aaa-8bbb-000000000100', 'body' => $body, 'unknown_field' => 'ignored'],
        ]));
        $message->setChannel($this->app->make(RabbitMqConnection::class)->channel());
        $message->setDeliveryTag(1);

        $this->app->make(EventConsumer::class)->process($message);
    }

    private function analyzed(): array
    {
        return array_values(array_filter($this->published, fn ($sent) => $sent[0] === 'review.analyzed'));
    }

    private function token(string $role): array
    {
        $token = JWT::encode(
            ['sub' => '0193a7c0-1111-7aaa-8bbb-000000000001', 'role' => $role, 'name' => 'Tonio', 'exp' => time() + 1800],
            file_get_contents(base_path('tests/Fixtures/jwt/test-private.pem')),
            'RS256',
        );

        return ['Authorization' => "Bearer $token"];
    }

    public function test_the_table_matches_the_erd(): void
    {
        $columns = collect(DB::select(
            "select column_name, data_type, character_maximum_length, numeric_precision, numeric_scale, is_nullable
             from information_schema.columns where table_schema = current_schema() and table_name = 'sentiment_results' order by ordinal_position"
        ))->mapWithKeys(fn ($c) => [$c->column_name => implode(' ', array_filter([
            $c->data_type,
            $c->character_maximum_length ? "({$c->character_maximum_length})" : null,
            $c->data_type === 'numeric' ? "({$c->numeric_precision},{$c->numeric_scale})" : null,
            $c->is_nullable === 'YES' ? 'NULL' : null,
        ]))])->all();

        $this->assertSame([
            'id' => 'uuid',
            'review_id' => 'uuid',
            'label' => 'character varying (20)',
            'score' => 'numeric (5,4)',
            'created_at' => 'timestamp without time zone NULL',
            'updated_at' => 'timestamp without time zone NULL',
        ], $columns);

        $this->assertContains('sentiment_results_review_id_unique', collect(DB::select("select indexname from pg_indexes where tablename = 'sentiment_results'"))->pluck('indexname'));
    }

    public function test_the_database_refuses_an_unknown_label(): void
    {
        $this->expectExceptionMessage('sentiment_results_label_check');

        DB::table('sentiment_results')->insert(['id' => (string) Str::uuid(), 'review_id' => self::REVIEW, 'label' => 'angry', 'score' => 0.5]);
    }

    public function test_the_handler_is_bound_to_both_review_events(): void
    {
        $this->assertSame(AnalyzeReviewHandler::class, config('events.handlers.ReviewCreated'));
        $this->assertSame(AnalyzeReviewHandler::class, config('events.handlers.ReviewUpdated'));
        $this->assertContains('review.created', config('events.bindings'));
        $this->assertContains('review.updated', config('events.bindings'));
    }

    public function test_a_new_review_is_analyzed_and_review_analyzed_is_published_after_commit(): void
    {
        $this->deliver('ReviewCreated', 'Loved the ending, the soundtrack is amazing.');

        $result = SentimentResult::where('review_id', self::REVIEW)->sole();
        $this->assertSame('positive', $result->label->value);
        $this->assertSame(0.94, $result->score);
        Http::assertSent(fn ($request) => $request['analysisInput']['documents'][0]['text'] === 'Loved the ending, the soundtrack is amazing.');

        $this->assertSame([['review.analyzed', ['review_id' => self::REVIEW, 'label' => 'positive', 'score' => 0.94], 1]], $this->analyzed(),
            'Published once, with the agreed payload, only after the consumer transaction committed (back to the test level 1).');
    }

    public function test_an_edited_review_is_analyzed_again_and_keeps_one_row(): void
    {
        $this->deliver('ReviewCreated', 'Loved the ending, the soundtrack is amazing.');
        $this->scores = ['positive' => 0.02, 'neutral' => 0.08, 'negative' => 0.9];

        $this->deliver('ReviewUpdated', 'Actually it was boring the second time.');

        $this->assertSame(1, SentimentResult::where('review_id', self::REVIEW)->count());
        $this->assertSame('negative', SentimentResult::where('review_id', self::REVIEW)->sole()->label->value);
        $this->assertSame(['positive', 'negative'], array_map(fn ($sent) => $sent[1]['label'], $this->analyzed()));
    }

    public function test_the_same_event_processed_twice_gives_one_row_and_one_call(): void
    {
        $id = (string) Str::uuid();

        $this->deliver('ReviewCreated', 'Loved the ending, the soundtrack is amazing.', $id);
        $this->deliver('ReviewCreated', 'Loved the ending, the soundtrack is amazing.', $id);

        $this->assertSame(1, SentimentResult::where('review_id', self::REVIEW)->count());
        Http::assertSentCount(1);
        $this->assertCount(1, $this->analyzed());
    }

    public function test_azure_down_saves_nothing_and_publishes_nothing(): void
    {
        $this->azureDown = true;

        $this->deliver('ReviewCreated', 'Loved the ending, the soundtrack is amazing.');

        $this->assertSame(0, SentimentResult::where('review_id', self::REVIEW)->count());
        $this->assertSame([], $this->analyzed());
        Http::assertSentCount(1 + EventConsumer::RETRIES);
    }

    public function test_an_admin_can_analyze_a_text(): void
    {
        $this->json('POST', '/api/sentiment/analyze', ['text' => 'What a wonderful film.'], $this->token('admin'))
            ->seeStatusCode(200)
            ->seeJsonEquals(['data' => ['label' => 'positive', 'score' => 0.94]]);

        $this->assertSame(0, SentimentResult::count(), 'Manual analysis stores nothing.');
    }

    public function test_only_admins_can_use_the_endpoint(): void
    {
        $this->json('POST', '/api/sentiment/analyze', ['text' => 'What a wonderful film.'])->seeStatusCode(401);
        $this->json('POST', '/api/sentiment/analyze', ['text' => 'What a wonderful film.'], $this->token('user'))->seeStatusCode(403);

        Http::assertNothingSent();
    }

    public function test_the_endpoint_validates_and_reports_azure_down(): void
    {
        foreach ([[], ['text' => '   '], ['text' => str_repeat('a', 5001)]] as $body) {
            $this->json('POST', '/api/sentiment/analyze', $body, $this->token('admin'))->seeStatusCode(422);
        }

        $this->azureDown = true;
        $this->json('POST', '/api/sentiment/analyze', ['text' => 'What a wonderful film.'], $this->token('admin'))
            ->seeStatusCode(503)
            ->seeJsonEquals(['message' => 'Azure AI Language is unavailable.']);
    }
}
