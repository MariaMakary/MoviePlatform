<?php

namespace MoviePlatform\Common\Integration;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use PhpAmqpLib\Message\AMQPMessage;

class EventPublisher
{
    public function __construct(private RabbitMqConnection $rabbitMq)
    {
    }

    public function publish(string $routingKey, array $data): array
    {
        $envelope = [
            'event' => self::eventName($routingKey),
            'id' => (string) Str::uuid(),
            'occurred_at' => Carbon::now('UTC')->toIso8601ZuluString(),
            'data' => $data,
        ];

        $message = new AMQPMessage(
            json_encode([...$envelope, 'data' => (object) $data], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            [
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                'content_type' => 'application/json',
                'message_id' => $envelope['id'],
            ],
        );

        $this->rabbitMq->channel()->basic_publish($message, $this->rabbitMq->exchange(), $routingKey);

        return $envelope;
    }

    public static function eventName(string $routingKey): string
    {
        return Str::studly(str_replace('.', '_', $routingKey));
    }
}
