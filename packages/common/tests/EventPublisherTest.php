<?php

namespace MoviePlatform\Common\Tests;

use Illuminate\Support\Str;
use MoviePlatform\Common\CommonServiceProvider;
use MoviePlatform\Common\Integration\EventPublisher;
use MoviePlatform\Common\Integration\RabbitMqConnection;
use Orchestra\Testbench\TestCase;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

class EventPublisherTest extends TestCase
{
    private array $published = [];

    protected function getPackageProviders($app): array
    {
        return [CommonServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $channel = $this->createMock(AMQPChannel::class);
        $channel->method('basic_publish')->willReturnCallback(function (AMQPMessage $message, string $exchange, string $routingKey) {
            $this->published[] = compact('message', 'exchange', 'routingKey');
        });

        $rabbitMq = $this->createMock(RabbitMqConnection::class);
        $rabbitMq->method('channel')->willReturn($channel);
        $rabbitMq->method('exchange')->willReturn('movieplatform.events');

        $this->app->instance(RabbitMqConnection::class, $rabbitMq);
    }

    private function publish(string $routingKey, array $data = []): array
    {
        $envelope = app(EventPublisher::class)->publish($routingKey, $data);

        $this->assertCount(1, $this->published);

        return [$envelope, $this->published[0]];
    }

    public function test_envelope_has_the_four_fields(): void
    {
        $data = ['user_id' => '0193a7c0-1111-7aaa-8bbb-000000000001', 'name' => 'Maria Makary', 'email' => 'maria@example.com'];

        [, $sent] = $this->publish('user.registered', $data);

        $body = json_decode($sent['message']->getBody(), true);

        $this->assertSame(['event', 'id', 'occurred_at', 'data'], array_keys($body));
        $this->assertSame('UserRegistered', $body['event']);
        $this->assertTrue(Str::isUuid($body['id']));
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $body['occurred_at']);
        $this->assertSame($data, $body['data']);
    }

    #[DataProvider('routingKeys')]
    public function test_event_name_is_built_from_the_routing_key(string $routingKey, string $event): void
    {
        [$envelope] = $this->publish($routingKey);

        $this->assertSame($event, $envelope['event']);
    }

    public static function routingKeys(): array
    {
        return [
            ['review.created', 'ReviewCreated'],
            ['user.genres_updated', 'UserGenresUpdated'],
            ['movie_night.invite_sent', 'MovieNightInviteSent'],
        ];
    }

    public function test_message_goes_to_the_exchange_with_the_routing_key(): void
    {
        [, $sent] = $this->publish('review.created');

        $this->assertSame('movieplatform.events', $sent['exchange']);
        $this->assertSame('review.created', $sent['routingKey']);
    }

    public function test_message_is_persistent_json_with_the_envelope_id(): void
    {
        [$envelope, $sent] = $this->publish('review.created');

        $properties = $sent['message']->get_properties();

        $this->assertSame(AMQPMessage::DELIVERY_MODE_PERSISTENT, $properties['delivery_mode']);
        $this->assertSame('application/json', $properties['content_type']);
        $this->assertSame($envelope['id'], $properties['message_id']);
    }

    public function test_empty_data_is_sent_as_an_object(): void
    {
        [, $sent] = $this->publish('review.created');

        $this->assertStringContainsString('"data":{}', $sent['message']->getBody());
    }

    public function test_each_message_gets_a_new_id(): void
    {
        $publisher = app(EventPublisher::class);

        $this->assertNotSame(
            $publisher->publish('review.created', [])['id'],
            $publisher->publish('review.created', [])['id'],
        );
    }

    public function test_missing_credentials_fail_clearly(): void
    {
        $connection = new RabbitMqConnection(['user' => '', 'password' => '', 'exchange' => 'movieplatform.events']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('secrets/rabbitmq_user');

        $connection->channel();
    }
}
