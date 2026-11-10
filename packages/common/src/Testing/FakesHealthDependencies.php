<?php

namespace MoviePlatform\Common\Testing;

use MoviePlatform\Common\Health\AmqpConnector;
use RuntimeException;

trait FakesHealthDependencies
{
    protected function fakeDatabase(bool $up): void
    {
        config([
            'database.default' => 'health_fake',
            'database.connections.health_fake' => [
                'driver' => 'sqlite',
                'database' => $up ? ':memory:' : sys_get_temp_dir().DIRECTORY_SEPARATOR.'missing-'.uniqid().'.sqlite',
                'prefix' => '',
            ],
        ]);
    }

    protected function fakeBroker(bool $up): void
    {
        $this->app->instance(AmqpConnector::class, new class($up) extends AmqpConnector
        {
            public function __construct(private bool $up)
            {
            }

            public function connect(float $timeout): void
            {
                if (! $this->up) {
                    throw new RuntimeException('Connection refused: amqp://guest:s3cret@127.0.0.1:5672');
                }
            }
        });
    }
}
