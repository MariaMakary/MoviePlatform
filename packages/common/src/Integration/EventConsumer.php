<?php

namespace MoviePlatform\Common\Integration;

use Illuminate\Contracts\Container\Container;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use JsonException;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use Throwable;

class EventConsumer
{
    public const RETRIES = 3;

    public const PREFETCH = 10;

    public function __construct(
        private RabbitMqConnection $rabbitMq,
        private ConnectionInterface $db,
        private Container $container,
        private array $config,
    ) {
    }

    public function queue(): string
    {
        return $this->config['queue'];
    }

    public function deadLetterQueue(): string
    {
        return $this->queue().'.dlq';
    }

    public function declare(): void
    {
        $channel = $this->rabbitMq->channel();

        $channel->queue_declare($this->deadLetterQueue(), false, true, false, false);
        $channel->queue_declare($this->queue(), false, true, false, false, false, new AMQPTable([
            'x-dead-letter-exchange' => '',
            'x-dead-letter-routing-key' => $this->deadLetterQueue(),
        ]));

        foreach ($this->config['bindings'] ?? [] as $routingKey) {
            $channel->queue_bind($this->queue(), $this->rabbitMq->exchange(), $routingKey);
        }
    }

    public function consume(): void
    {
        $this->declare();

        $channel = $this->rabbitMq->channel();
        $channel->basic_qos(0, self::PREFETCH, false);
        $channel->basic_consume($this->queue(), '', false, false, false, false, fn (AMQPMessage $message) => $this->process($message));

        while ($channel->is_consuming()) {
            $channel->wait();
        }
    }

    public function replayDeadLetters(): int
    {
        $this->declare();

        $channel = $this->rabbitMq->channel();
        $moved = 0;

        while ($message = $channel->basic_get($this->deadLetterQueue())) {
            $channel->basic_publish(new AMQPMessage($message->getBody(), $this->replayProperties($message)), '', $this->queue());
            $message->ack();
            $moved++;
        }

        return $moved;
    }

    public function process(AMQPMessage $message): void
    {
        try {
            $envelope = json_decode($message->getBody(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $envelope = null;
        }

        if (! is_array($envelope) || ! is_string($envelope['id'] ?? null) || ! Str::isUuid($envelope['id'])) {
            Log::warning('Event moved to the dead-letter queue: not valid JSON or no uuid id', ['queue' => $this->queue()]);
            $message->reject(false);

            return;
        }

        $event = (string) ($envelope['event'] ?? '');
        $handler = $this->config['handlers'][$event] ?? null;

        if (! $handler) {
            $message->ack();

            return;
        }

        for ($attempt = 1; $attempt <= self::RETRIES + 1; $attempt++) {
            try {
                $this->handle($this->container->make($handler), $event, $envelope);
                $message->ack();

                return;
            } catch (Throwable $e) {
                Log::warning("Event $event failed (attempt $attempt)", ['event_id' => $envelope['id'], 'error' => get_class($e).': '.$e->getMessage()]);
            }
        }

        Log::error("Event $event moved to the dead-letter queue", ['event_id' => $envelope['id'], 'queue' => $this->deadLetterQueue()]);
        $message->reject(false);
    }

    private function handle(EventHandler $handler, string $event, array $envelope): void
    {
        $this->db->transaction(function () use ($handler, $event, $envelope) {
            if ($this->db->table('processed_events')->where('event_id', $envelope['id'])->exists()) {
                return;
            }

            $handler->handle((array) ($envelope['data'] ?? []), $envelope);

            $this->db->table('processed_events')->insert([
                'event_id' => $envelope['id'],
                'event' => $event,
                'processed_at' => Carbon::now('UTC'),
            ]);
        });
    }

    private function replayProperties(AMQPMessage $message): array
    {
        $properties = $message->get_properties();

        if (isset($properties['application_headers'])) {
            $headers = array_filter(
                $properties['application_headers']->getNativeData(),
                fn ($key) => ! str_starts_with($key, 'x-death') && ! str_starts_with($key, 'x-first-death') && ! str_starts_with($key, 'x-last-death'),
                ARRAY_FILTER_USE_KEY,
            );
            $properties['application_headers'] = new AMQPTable($headers);
        }

        return ['delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT] + $properties;
    }
}
