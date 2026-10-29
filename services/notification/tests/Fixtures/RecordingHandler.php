<?php

namespace Tests\Fixtures;

use Illuminate\Support\Facades\DB;
use MoviePlatform\Common\Integration\EventHandler;

class RecordingHandler implements EventHandler
{
    public function handle(array $data, array $envelope): void
    {
        DB::table('test_effects')->insert(['event_id' => $envelope['id'], 'user_id' => $data['user_id']]);
    }
}
