<?php

namespace Tests;

use Closure;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use MoviePlatform\Common\Integration\EventConsumer;
use MoviePlatform\Common\Integration\RabbitMqConnection;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use Tests\Fixtures\FailingHandler;
use Tests\Fixtures\RecordingHandler;

class EventConsumerTest extends TestCase
{
    private const USER_ID = '0193a7c0-1111-7aaa-8bbb-000000000001';

    private AMQPChannel $channel;

    private array $acked = [];

    private array $rejected = [];

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'testing',
            'database.connections.testing' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
        ]);
        DB::purge('testing');

        $this->artisan('migrate');
        Schema::create('test_effects', function (Blueprint $table) {
            $table->uuid('event_id');
            $table->uuid('user_id');
        });

        $this->channel = $this->createMock(AMQPChannel::class);
        $this->channel->method('basic_ack')->willReturnCallback(function ($tag) {
            $this->acked[] = $tag;
        });
        $this->channel->method('basic_reject')->willReturnCallback(function ($tag, $requeue) {
            $this->rejected[] = [$tag, $requeue];
        });

        $rabbitMq = $this->createMock(RabbitMqConnection::class);
        $rabbitMq->method('channel')->willReturn($this->channel);
        $rabbitMq->method('exchange')->willReturn('movieplatform.events');
        $this->app->instance(RabbitMqConnection::class, $rabbitMq);

        config(['events.handlers' => ['UserRegistered' => RecordingHandler::class, 'UserDeleted' => FailingHandler::class]]);
        FailingHandler::$calls = 0;
    }

    private function consumer(): EventConsumer
    {
        $this->app->forgetInstance(EventConsumer::class);

        return $this->app->make(EventConsumer::class);
    }

    private function message(string $body, int $tag = 1): AMQPMessage
    {
        $message = new AMQPMessage($body);
        $message->setChannel($this->channel);
        $message->setDeliveryTag($tag);

        return $message;
    }

    private function event(string $event, ?string $id = null): string
    {
        return json_encode([
            'event' => $event,
            'id' => $id ?? (string) Str::uuid(),
            'occurred_at' => '2026-10-29T10:00:00Z',
            'data' => ['user_id' => self::USER_ID, 'unknown_field' => 'ignored'],
        ]);
    }

    public function test_the_provider_registers_the_processed_events_migration(): void
    {
        $this->assertSame(['event_id', 'event', 'processed_at'], Schema::getColumnListing('processed_events'));
    }

    public function test_it_declares_durable_queues_with_a_dead_letter_queue_and_the_bindings(): void
    {
        $declared = [];
        $this->channel->method('queue_declare')->willReturnCallback(function ($queue, $passive, $durable, $exclusive, $autoDelete, $nowait = false, $arguments = []) use (&$declared) {
            $declared[$queue] = [$durable, $arguments instanceof AMQPTable ? $arguments->getNativeData() : []];

            return null;
        });
        $bound = [];
        $this->channel->method('queue_bind')->willReturnCallback(function ($queue, $exchange, $routingKey) use (&$bound) {
            $bound[] = "$queue:$exchange:$routingKey";

            return null;
        });

        $this->consumer()->declare();

        $this->assertSame([
            'notification.events.dlq' => [true, []],
            'notification.events' => [true, ['x-dead-letter-exchange' => '', 'x-dead-letter-routing-key' => 'notification.events.dlq']],
        ], $declared);
        $this->assertContains('notification.events:movieplatform.events:user.registered', $bound);
        $this->assertCount(count(config('events.bindings')), $bound);
    }

    public function test_it_consumes_with_manual_acks_and_prefetch_10_so_a_stopped_consumer_loses_nothing(): void
    {
        $this->channel->expects($this->once())->method('basic_qos')->with(0, 10, false);
        $this->channel->expects($this->once())->method('basic_consume')
            ->with('notification.events', '', false, false, false, false, $this->isInstanceOf(Closure::class));
        $this->channel->method('is_consuming')->willReturn(false);

        $this->consumer()->consume();
    }

    public function test_a_handled_event_is_acked_and_recorded(): void
    {
        $id = (string) Str::uuid();

        $this->consumer()->process($this->message($this->event('UserRegistered', $id)));

        $this->assertSame([1], $this->acked);
        $this->assertSame(1, DB::table('test_effects')->count());
        $this->assertSame('UserRegistered', DB::table('processed_events')->where('event_id', $id)->value('event'));
    }

    public function test_the_same_event_twice_has_no_double_effect(): void
    {
        $body = $this->event('UserRegistered');

        $this->consumer()->process($this->message($body, 1));
        $this->consumer()->process($this->message($body, 2));

        $this->assertSame([1, 2], $this->acked);
        $this->assertSame(1, DB::table('test_effects')->count());
        $this->assertSame(1, DB::table('processed_events')->count());
    }

    public function test_a_failing_handler_is_retried_3_times_then_goes_to_the_dlq(): void
    {
        $this->consumer()->process($this->message($this->event('UserDeleted')));

        $this->assertSame(4, FailingHandler::$calls);
        $this->assertSame([], $this->acked);
        $this->assertSame([[1, false]], $this->rejected);
        $this->assertSame(0, DB::table('test_effects')->count());
        $this->assertSame(0, DB::table('processed_events')->count());
    }

    public function test_an_event_without_a_handler_is_acked_and_skipped(): void
    {
        $this->consumer()->process($this->message($this->event('MovieNightFinalized')));

        $this->assertSame([1], $this->acked);
        $this->assertSame(0, DB::table('processed_events')->count());
    }

    public function test_a_broken_message_goes_straight_to_the_dlq(): void
    {
        $consumer = $this->consumer();

        $consumer->process($this->message('{not json', 1));
        $consumer->process($this->message(json_encode(['event' => 'UserRegistered', 'data' => []]), 2));
        $consumer->process($this->message(json_encode(['event' => 'UserRegistered', 'id' => 'abc', 'data' => []]), 3));

        $this->assertSame([[1, false], [2, false], [3, false]], $this->rejected);
        $this->assertSame([], $this->acked);
        $this->assertSame(0, DB::table('test_effects')->count());
    }

    public function test_replay_moves_dead_letters_back_to_the_main_queue(): void
    {
        $deadLetter = $this->message($this->event('UserDeleted'), 7);
        $deadLetter->set('application_headers', new AMQPTable(['x-death' => [['count' => 1]], 'x-correlation' => 'abc']));
        $this->channel->method('basic_get')->with('notification.events.dlq')->willReturnOnConsecutiveCalls($deadLetter, null);

        $published = [];
        $this->channel->method('basic_publish')->willReturnCallback(function (AMQPMessage $message, $exchange, $routingKey) use (&$published) {
            $published[] = [$message, $exchange, $routingKey];

            return null;
        });

        $this->assertSame(1, $this->consumer()->replayDeadLetters());
        $this->assertSame([7], $this->acked);
        [$message, $exchange, $routingKey] = $published[0];
        $this->assertSame(['', 'notification.events'], [$exchange, $routingKey]);
        $this->assertSame($deadLetter->getBody(), $message->getBody());
        $this->assertSame(['x-correlation' => 'abc'], $message->get('application_headers')->getNativeData());
        $this->assertSame(AMQPMessage::DELIVERY_MODE_PERSISTENT, $message->get('delivery_mode'));
    }
}
