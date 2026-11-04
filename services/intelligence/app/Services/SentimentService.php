<?php

namespace App\Services;

use App\Integration\Clients\SentimentClient;
use App\Integration\Events\ReviewAnalyzed;
use App\Models\SentimentResult;
use Illuminate\Support\Facades\DB;
use MoviePlatform\Common\Integration\EventPublisher;

class SentimentService
{
    public function __construct(private SentimentClient $client, private EventPublisher $events) {}

    /** @return array{label: string, score: float} */
    public function analyze(string $text): array
    {
        return $this->client->analyze($text);
    }

    /**
     * Analyzes a review's text and keeps one result per review (an edited review replaces its result).
     * Runs inside the consumer's transaction, so ReviewAnalyzed waits for its commit.
     */
    public function analyzeReview(string $reviewId, string $body): SentimentResult
    {
        $sentiment = $this->client->analyze($body);

        $result = SentimentResult::updateOrCreate(
            ['review_id' => $reviewId],
            ['label' => $sentiment['label'], 'score' => round($sentiment['score'], 4)],
        );

        DB::afterCommit(fn () => $this->events->publish(ReviewAnalyzed::ROUTING_KEY, ReviewAnalyzed::data($result)));

        return $result;
    }
}
