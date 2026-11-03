<?php

namespace App\Integration\Handlers;

use Illuminate\Support\Facades\Log;
use MoviePlatform\Common\Integration\EventHandler;

/** Logs every rating for now, to prove the event bus works end to end. Recommendations will use it later. */
class RatingAddedHandler implements EventHandler
{
    public function handle(array $data, array $envelope): void
    {
        Log::info('Rating added', [
            'rating_id' => $data['rating_id'],
            'user_id' => $data['user_id'],
            'movie_id' => $data['movie_id'],
            'score' => $data['score'],
        ]);
    }
}
