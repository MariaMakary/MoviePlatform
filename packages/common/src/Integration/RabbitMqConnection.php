<?php

namespace MoviePlatform\Common\Integration;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use RuntimeException;

class RabbitMqConnection
{
    private ?AMQPStreamConnection $connection = null;

    private ?AMQPChannel $channel = null;

    public function __construct(private array $config)
    {
    }

    public function exchange(): string
    {
        return $this->config['exchange'];
    }

    public function channel(): AMQPChannel
    {
        if ($this->channel?->is_open()) {
            return $this->channel;
        }

        if (($this->config['user'] ?? '') === '' || ($this->config['password'] ?? '') === '') {
            throw new RuntimeException('RabbitMQ credentials missing: check secrets/rabbitmq_user and secrets/rabbitmq_password.');
        }

        $this->connection = new AMQPStreamConnection(
            $this->config['host'],
            $this->config['port'],
            $this->config['user'],
            $this->config['password'],
            $this->config['vhost'],
        );

        $this->channel = $this->connection->channel();
        $this->channel->exchange_declare($this->exchange(), 'topic', false, true, false);

        return $this->channel;
    }

    public function close(): void
    {
        $this->channel?->close();
        $this->connection?->close();
        $this->channel = null;
        $this->connection = null;
    }
}
