<?php

namespace MoviePlatform\Common\Console;

use Illuminate\Console\Command;
use MoviePlatform\Common\Integration\EventConsumer;

class ReplayDeadLetters extends Command
{
    protected $signature = 'events:replay-dlq';

    protected $description = 'Move the dead letters of this service back to its main queue';

    public function handle(EventConsumer $consumer): int
    {
        $moved = $consumer->replayDeadLetters();

        $this->info("Moved $moved message(s) from {$consumer->deadLetterQueue()} to {$consumer->queue()}");

        return self::SUCCESS;
    }
}
