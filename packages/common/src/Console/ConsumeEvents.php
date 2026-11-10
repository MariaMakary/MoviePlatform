<?php

namespace MoviePlatform\Common\Console;

use Illuminate\Console\Command;
use MoviePlatform\Common\Integration\EventConsumer;

class ConsumeEvents extends Command
{
    protected $signature = 'events:consume';

    protected $description = 'Consume this service\'s RabbitMQ queue and run the handlers of config/events.php';

    public function handle(EventConsumer $consumer): int
    {
        $this->info("Consuming {$consumer->queue()} (Ctrl+C to stop)");

        $consumer->consume();

        return self::SUCCESS;
    }
}
