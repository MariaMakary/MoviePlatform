<?php

namespace MoviePlatform\Common\Health;

use PhpAmqpLib\Connection\AMQPConnectionConfig;
use PhpAmqpLib\Connection\AMQPConnectionFactory;
use Throwable;

class AmqpConnector
{
    public function connect(float $timeout): void
    {
        $config = new AMQPConnectionConfig();
        $config->setHost(config('rabbitmq.host'));
        $config->setPort((int) config('rabbitmq.port'));
        $config->setVhost(config('rabbitmq.vhost'));
        $config->setUser(config('rabbitmq.user'));
        $config->setPassword(config('rabbitmq.password'));
        $config->setConnectionTimeout($timeout);
        $config->setReadTimeout($timeout);
        $config->setWriteTimeout($timeout);
        $config->setHeartbeat(0);

        $connection = AMQPConnectionFactory::create($config);

        try {
            $connection->close();
        } catch (Throwable) {
        }
    }
}
