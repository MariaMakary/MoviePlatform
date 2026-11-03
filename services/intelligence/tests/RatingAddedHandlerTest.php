<?php

namespace Tests;

use App\Integration\Handlers\RatingAddedHandler;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Lumen\Testing\DatabaseTransactions;
use MoviePlatform\Common\Integration\EventConsumer;
use MoviePlatform\Common\Integration\RabbitMqConnection;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;

class RatingAddedHandlerTest extends TestCase
{
    use DatabaseTransactions;

    private const DATA = [
        'rating_id' => '0193a7d0-5555-7aaa-8bbb-000000000300',
        'user_id' => '0193a7c0-1111-7aaa-8bbb-000000000001',
        'movie_id' => '0193a7c1-3333-7aaa-8bbb-000000000100',
        'score' => 4,
    ];

    private AMQPChannel $channel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->channel = $this->createMock(AMQPChannel::class);

        $rabbitMq = $this->createMock(RabbitMqConnection::class);
        $rabbitMq->method('channel')->willReturn($this->channel);
        $this->app->instance(RabbitMqConnection::class, $rabbitMq);

        Log::spy();
    }

    private function deliver(string $id, array $data = []): void
    {
        $message = new AMQPMessage(json_encode([
            'event' => 'RatingAdded',
            'id' => $id,
            'occurred_at' => '2026-11-02T10:00:00Z',
            'data' => $data + self::DATA,
        ]));
        $message->setChannel($this->channel);
        $message->setDeliveryTag(1);

        $this->app->make(EventConsumer::class)->process($message);
    }

    public function test_the_handler_is_registered_for_rating_added(): void
    {
        $this->assertSame(RatingAddedHandler::class, config('events.handlers.RatingAdded'));
    }

    public function test_a_rating_is_logged_with_its_fields(): void
    {
        $id = (string) Str::uuid();

        $this->deliver($id);

        Log::shouldHaveReceived('info')->once()->with('Rating added', self::DATA);
        $this->assertSame('RatingAdded', DB::table('processed_events')->where('event_id', $id)->value('event'));
    }

    public function test_the_same_event_twice_is_logged_once(): void
    {
        $id = (string) Str::uuid();

        $this->deliver($id);
        $this->deliver($id);

        Log::shouldHaveReceived('info')->once()->with('Rating added', self::DATA);
    }

    public function test_unknown_fields_are_ignored(): void
    {
        $this->deliver((string) Str::uuid(), ['comment' => 'not in the contract']);

        Log::shouldHaveReceived('info')->once()->with('Rating added', self::DATA);
    }
}
