<?php

namespace App\Services;

use App\Integration\Events\ReviewCreated;
use App\Integration\Events\ReviewUpdated;
use App\Models\Review;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use MoviePlatform\Common\Integration\EventPublisher;

class ReviewService
{
    public function __construct(private EventPublisher $events) {}

    public function forMovie(string $movieId): Builder
    {
        return $this->listed()->where('movie_id', $movieId);
    }

    public function forUser(string $userId): Builder
    {
        return $this->listed()->where('user_id', $userId);
    }

    public function find(string $id): Review
    {
        if (! Str::isUuid($id)) {
            throw (new ModelNotFoundException)->setModel(Review::class);
        }

        return Review::withAuthorScore()->findOrFail($id);
    }

    // The unique key decides, so two posts at the same time still give one review.
    public function create(string $userId, string $movieId, string $body): Review
    {
        try {
            $review = DB::transaction(fn () => Review::create(['user_id' => $userId, 'movie_id' => $movieId, 'body' => $body]));
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['movie_id' => ['You already reviewed this movie.']]);
        }

        $this->events->publish(ReviewCreated::ROUTING_KEY, ReviewCreated::data($review));

        return $this->find($review->id);
    }

    public function update(Review $review, string $body): Review
    {
        if ($body === $review->body) {
            return $review;
        }

        DB::transaction(fn () => $review->update(['body' => $body]));

        $this->events->publish(ReviewUpdated::ROUTING_KEY, ReviewUpdated::data($review));

        return $this->find($review->id);
    }

    public function delete(Review $review): void
    {
        $review->delete();
    }

    /** Every review for moderation, hidden and author-blocked ones included, newest first. Empty filters are ignored. */
    public function forAdmin(array $filters): Builder
    {
        $query = Review::query()->select('reviews.*')->withAuthorScore()->latest()->orderByDesc('id');

        if (($filters['sentiment'] ?? '') !== '') {
            $query->where('sentiment_label', $filters['sentiment']);
        }

        if (($filters['movie_id'] ?? '') !== '') {
            $query->where('movie_id', $filters['movie_id']);
        }

        if (($filters['hidden'] ?? '') !== '') {
            $query->where('is_hidden', filter_var($filters['hidden'], FILTER_VALIDATE_BOOLEAN));
        }

        return $query;
    }

    /** Admin moderation: only `is_hidden`, without touching `updated_at` (not an edit by the author). */
    public function setHidden(Review $review, bool $hidden): Review
    {
        Review::whereKey($review->id)->toBase()->update(['is_hidden' => $hidden]);

        return $this->find($review->id);
    }

    private function listed(): Builder
    {
        return Review::query()->select('reviews.*')->withAuthorScore()->visible()->latest()->orderByDesc('id');
    }
}
