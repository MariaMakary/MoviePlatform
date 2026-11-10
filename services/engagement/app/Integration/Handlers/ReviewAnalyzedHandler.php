<?php

namespace App\Integration\Handlers;

use App\Enums\SentimentLabel;
use App\Models\Review;
use InvalidArgumentException;
use MoviePlatform\Common\Integration\EventHandler;

/** Stores Intelligence's sentiment on the review, for the badge of the reviews section (1182). */
class ReviewAnalyzedHandler implements EventHandler
{
    public function handle(array $data, array $envelope): void
    {
        $review = Review::find($data['review_id']);

        // Deleted while it was being analyzed: nothing to update, the event is acknowledged.
        if ($review === null) {
            return;
        }

        // An unknown label or a score outside 0–1 (docs/events.md) throws, so the event ends in the DLQ
        // instead of storing something wrong.
        $review->sentiment_label = SentimentLabel::from($data['label']);
        $review->sentiment_score = $this->score($data['score'] ?? null);

        // The sentiment is not an edit by the author: updated_at stays as it is.
        $review->timestamps = false;
        $review->save();
    }

    private function score(mixed $score): float
    {
        if (! is_int($score) && ! is_float($score) || $score < 0 || $score > 1) {
            throw new InvalidArgumentException('ReviewAnalyzed score must be a number from 0 to 1.');
        }

        return round((float) $score, 4);
    }
}
