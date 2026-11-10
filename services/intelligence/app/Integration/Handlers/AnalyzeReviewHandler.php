<?php

namespace App\Integration\Handlers;

use App\Services\SentimentService;
use MoviePlatform\Common\Integration\EventHandler;

class AnalyzeReviewHandler implements EventHandler
{
    public function __construct(private SentimentService $sentiment) {}

    public function handle(array $data, array $envelope): void
    {
        $this->sentiment->analyzeReview($data['review_id'], $data['body']);
    }
}
