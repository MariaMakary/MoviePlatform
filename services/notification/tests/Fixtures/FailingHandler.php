<?php

namespace Tests\Fixtures;

use Illuminate\Support\Facades\DB;
use MoviePlatform\Common\Integration\EventHandler;
use RuntimeException;

class FailingHandler implements EventHandler
{
    public static int $calls = 0;

    public function handle(array $data, array $envelope): void
    {
        static::$calls++;
        DB::table('test_effects')->insert(['event_id' => $envelope['id'], 'user_id' => $data['user_id']]);

        throw new RuntimeException('Notification store is down');
    }
}
