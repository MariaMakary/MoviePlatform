<?php

namespace Tests;

use MoviePlatform\Common\Integration\EventPublisher;
use MoviePlatform\Common\Integration\RabbitMqConnection;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;

class EventPublisherTest extends TestCase
{
    public function test_rabbitmq_settings_are_loaded(): void
    {
        $this->assertSame('movieplatform.events', config('rabbitmq.exchange'));
        $this->assertSame(5672, config('rabbitmq.port'));
    }

    public function test_publisher_sends_the_envelope_from_a_lumen_service(): void
    {
        $sent = null;

        $channel = $this->createMock(AMQPChannel::class);
        $channel->expects($this->once())->method('basic_publish')
            ->willReturnCallback(function (AMQPMessage $message, string $exchange, string $routingKey) use (&$sent) {
                $sent = [json_decode($message->getBody(), true), $exchange, $routingKey];
            });

        $rabbitMq = $this->createMock(RabbitMqConnection::class);
        $rabbitMq->method('channel')->willReturn($channel);
        $rabbitMq->method('exchange')->willReturn('movieplatform.events');
        $this->app->instance(RabbitMqConnection::class, $rabbitMq);

        $envelope = app(EventPublisher::class)->publish('user.registered', ['user_id' => '0193a7c0-1111-7aaa-8bbb-000000000001']);

        [$body, $exchange, $routingKey] = $sent;
        $this->assertSame('UserRegistered', $body['event']);
        $this->assertSame($envelope['id'], $body['id']);
        $this->assertSame('movieplatform.events', $exchange);
        $this->assertSame('user.registered', $routingKey);
    }
}
