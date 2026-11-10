<?php

namespace Tests;

use App\Integration\Handlers\ReviewAnalyzedHandler;
use App\Models\Review;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Lumen\Testing\DatabaseTransactions;
use MoviePlatform\Common\Integration\EventConsumer;
use MoviePlatform\Common\Integration\RabbitMqConnection;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;

class ReviewAnalyzedHandlerTest extends TestCase
{
    use DatabaseTransactions;

    private const USER = '0193a7c0-1111-7aaa-8bbb-000000000001';

    private const MOVIE = '0193a7c1-3333-7aaa-8bbb-000000000100';

    private AMQPChannel $channel;

    /** What the consumer answered RabbitMQ: 'ack', or 'reject' (no requeue = to the DLQ). */
    private array $answers = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->channel = $this->createMock(AMQPChannel::class);
        $this->channel->method('basic_ack')->willReturnCallback(function () {
            $this->answers[] = 'ack';
        });
        $this->channel->method('basic_reject')->willReturnCallback(function ($deliveryTag, bool $requeue) {
            $this->answers[] = $requeue ? 'requeue' : 'reject';
        });

        $rabbitMq = $this->createMock(RabbitMqConnection::class);
        $rabbitMq->method('channel')->willReturn($this->channel);
        $this->app->instance(RabbitMqConnection::class, $rabbitMq);
    }

    private function review(): Review
    {
        return Review::forceCreate([
            'user_id' => self::USER, 'movie_id' => self::MOVIE, 'body' => 'Loved the ending, the soundtrack is amazing.',
            'created_at' => Carbon::parse('2026-11-01T10:00:00Z'), 'updated_at' => Carbon::parse('2026-11-01T10:00:00Z'),
        ]);
    }

    private function deliver(string $reviewId, string $label = 'positive', mixed $score = 0.9731, ?string $id = null): string
    {
        $id ??= (string) Str::uuid();
        $message = new AMQPMessage(json_encode([
            'event' => 'ReviewAnalyzed',
            'id' => $id,
            'occurred_at' => '2026-11-04T10:00:00Z',
            'data' => ['review_id' => $reviewId, 'label' => $label, 'score' => $score, 'unknown_field' => 'ignored'],
        ]));
        $message->setChannel($this->channel);
        $message->setDeliveryTag(1);

        $this->app->make(EventConsumer::class)->process($message);

        return $id;
    }

    public function test_the_handler_is_bound_to_review_analyzed(): void
    {
        $this->assertSame(ReviewAnalyzedHandler::class, config('events.handlers.ReviewAnalyzed'));
        $this->assertContains('review.analyzed', config('events.bindings'));
    }

    public function test_the_sentiment_is_saved_on_the_review(): void
    {
        $review = $this->review();

        $this->deliver($review->id, 'positive', 0.97314);

        $review->refresh();
        $this->assertSame('positive', $review->sentiment_label->value);
        $this->assertSame(0.9731, $review->sentiment_score);
        $this->assertSame('2026-11-01T10:00:00Z', $review->updated_at->toIso8601ZuluString(), 'The sentiment is not an edit.');
    }

    public function test_the_sentiment_shows_in_the_reviews_list(): void
    {
        $review = $this->review();

        $this->deliver($review->id, 'negative', 0.88);

        $this->get('/api/movies/'.self::MOVIE.'/reviews')
            ->seeStatusCode(200)
            ->seeJson(['id' => $review->id, 'sentiment_label' => 'negative', 'sentiment_score' => 0.88]);
    }

    public function test_a_newer_analysis_replaces_the_older_one(): void
    {
        $review = $this->review();

        $this->deliver($review->id, 'positive', 0.9);
        $this->deliver($review->id, 'neutral', 0.6);

        $this->assertSame('neutral', $review->fresh()->sentiment_label->value);
    }

    public function test_a_review_deleted_in_the_meantime_is_acknowledged_and_skipped(): void
    {
        $id = $this->deliver('0193a7d1-9999-7aaa-8bbb-000000000999');

        $this->assertTrue(DB::table('processed_events')->where('event_id', $id)->exists(), 'The event is done, not retried.');
        $this->assertSame(['ack'], $this->answers, 'Acknowledged, nothing goes to the DLQ.');
    }

    public function test_the_same_event_twice_is_applied_once(): void
    {
        $review = $this->review();
        $id = $this->deliver($review->id, 'positive', 0.9);
        $review->forceFill(['sentiment_label' => 'neutral'])->save();

        $this->deliver($review->id, 'positive', 0.9, $id);

        $this->assertSame('neutral', $review->fresh()->sentiment_label->value, 'The second copy was skipped.');
        $this->assertSame(1, DB::table('processed_events')->where('event_id', $id)->count());
    }

    public function test_a_score_outside_0_to_1_or_not_a_number_goes_to_the_dlq_and_changes_nothing(): void
    {
        foreach ([1.7, -0.1, 'abc', '0.9', null] as $score) {
            $review = $this->review();
            $this->answers = [];

            $this->deliver($review->id, 'positive', $score);

            $review->refresh();
            $this->assertNull($review->sentiment_label, 'score '.var_export($score, true));
            $this->assertNull($review->sentiment_score);
            $this->assertSame(['reject'], $this->answers, 'score '.var_export($score, true).' must go to the DLQ.');
            $review->delete();
        }
    }

    public function test_the_limits_0_and_1_are_accepted(): void
    {
        foreach ([0, 1] as $score) {
            $review = $this->review();

            $this->deliver($review->id, 'neutral', $score);

            $this->assertSame((float) $score, $review->fresh()->sentiment_score);
            $review->delete();
        }
    }

    public function test_an_unknown_label_goes_to_the_dlq_and_changes_nothing(): void
    {
        $review = $this->review();

        $this->deliver($review->id, 'angry', 0.9);

        $this->assertNull($review->fresh()->sentiment_label);
        $this->assertSame(['reject'], $this->answers, 'Rejected without requeue, so RabbitMQ moves it to engagement.events.dlq.');
    }
}
