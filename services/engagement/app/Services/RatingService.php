<?php

namespace App\Services;

use App\Integration\Events\RatingAdded;
use App\Models\Rating;
use MoviePlatform\Common\Integration\EventPublisher;

class RatingService
{
    public function __construct(private EventPublisher $events) {}

    // createOrFirst relies on the unique key, so two first ratings at the same time still give one row.
    public function rate(string $userId, string $movieId, int $score): Rating
    {
        $rating = Rating::createOrFirst(['user_id' => $userId, 'movie_id' => $movieId], ['score' => $score]);

        if (! $rating->wasRecentlyCreated) {
            $rating->update(['score' => $score]);
        }

        $this->events->publish(RatingAdded::ROUTING_KEY, RatingAdded::data($rating));

        return $rating;
    }

    public function summaryFor(string $movieId, ?string $userId): array
    {
        $stats = Rating::where('movie_id', $movieId)->toBase()->selectRaw('avg(score) as average, count(*) as count')->first();

        return [
            'average' => $stats->average === null ? null : round((float) $stats->average, 2),
            'count' => (int) $stats->count,
            'my_score' => $userId === null ? null : Rating::ownedBy($userId)->where('movie_id', $movieId)->value('score'),
        ];
    }
}
