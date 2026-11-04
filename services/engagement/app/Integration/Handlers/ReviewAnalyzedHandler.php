<?php

namespace App\Integration\Handlers;

use App\Enums\SentimentLabel;
use App\Models\Review;
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

        // An unknown label throws, so the event ends in the DLQ instead of storing something wrong.
        $review->sentiment_label = SentimentLabel::from($data['label']);
        $review->sentiment_score = round((float) $data['score'], 4);

        // The sentiment is not an edit by the author: updated_at stays as it is.
        $review->timestamps = false;
        $review->save();
    }
}
