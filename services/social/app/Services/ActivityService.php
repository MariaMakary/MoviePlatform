<?php

namespace App\Services;

use App\Enums\ActivityType;
use App\Models\Activity;
use App\Models\Follow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Turns Engagement events into feed entries (docs/events.md). Runs inside the event consumer's transaction, which also
 * makes an event that arrives twice run once; activities.event_id UNIQUE is the second guard.
 */
class ActivityService
{
    /** How much of a review the feed shows. */
    public const EXCERPT_LENGTH = 280;

    public function record(string $event, array $data, array $envelope): void
    {
        $at = Carbon::parse($envelope['occurred_at'] ?? 'now')->utc();

        match ($event) {
            'RatingAdded' => $this->rated($data, $envelope['id'], $at),
            'ReviewCreated' => $this->reviewCreated($data, $envelope['id'], $at),
            'ReviewUpdated' => $this->reviewUpdated($data, $envelope['id'], $at),
            'MovieWatched' => $this->create(ActivityType::Watched, $envelope['id'], $data['user_id'], $data['movie_id'], $data['movie_id'], ['watched_at' => $data['watched_at'] ?? null], $at),
            'ListCreated' => $this->listCreated($data, $envelope['id'], $at),
            default => null,
        };
    }

    /** GET /api/feed: what the people $userId follows did, newest first, as their privacy allows. */
    public function feedFor(string $userId): Builder
    {
        return Activity::allowedByPrivacy()
            ->authorNotBlocked()
            ->whereIn('activities.user_id', Follow::followedBy($userId)->select('following_id'))
            ->newestFirst();
    }

    /** GET /api/users/{id}/activity: the user sees all of their own; anyone else what their privacy allows. */
    public function ofUser(string $userId, ?string $viewerId): Builder
    {
        $query = $viewerId === $userId ? Activity::query() : Activity::allowedByPrivacy();

        return $query->authorNotBlocked()->where('activities.user_id', $userId)->newestFirst();
    }

    /** Feed entries about a movie that left Catalog go with it (MovieDeleted). */
    public function removeMovie(string $movieId): void
    {
        Activity::where('movie_id', $movieId)->delete();
    }

    // A changed rating updates its entry and moves it to the top, instead of adding one per change.
    private function rated(array $data, string $eventId, Carbon $at): void
    {
        $existing = $this->find(ActivityType::Rating, $data['rating_id']);

        if ($existing !== null) {
            $existing->update(['data' => ['score' => (int) $data['score']], 'created_at' => $at]);

            return;
        }

        $this->create(ActivityType::Rating, $eventId, $data['user_id'], $data['rating_id'], $data['movie_id'], ['score' => (int) $data['score']], $at);
    }

    private function reviewCreated(array $data, string $eventId, Carbon $at): void
    {
        $this->create(ActivityType::Review, $eventId, $data['user_id'], $data['review_id'], $data['movie_id'], ['excerpt' => $this->excerpt($data['body'])], $at);
    }

    // An edited review keeps its place in the feed, with the new text.
    private function reviewUpdated(array $data, string $eventId, Carbon $at): void
    {
        $existing = $this->find(ActivityType::Review, $data['review_id']);

        if ($existing !== null) {
            $existing->update(['data' => ['excerpt' => $this->excerpt($data['body'])]]);

            return;
        }

        $this->reviewCreated($data, $eventId, $at);
    }

    // Private lists never reach the feed.
    private function listCreated(array $data, string $eventId, Carbon $at): void
    {
        if (($data['is_public'] ?? false) === true) {
            $this->create(ActivityType::List, $eventId, $data['user_id'], $data['list_id'], null, ['title' => $data['title']], $at);
        }
    }

    private function create(ActivityType $type, string $eventId, string $userId, string $subjectId, ?string $movieId, array $data, Carbon $at): void
    {
        Activity::firstOrCreate(['event_id' => $eventId], [
            'user_id' => $userId, 'type' => $type, 'subject_id' => $subjectId, 'movie_id' => $movieId, 'data' => $data, 'created_at' => $at,
        ]);
    }

    private function find(ActivityType $type, string $subjectId): ?Activity
    {
        return Activity::where('type', $type)->where('subject_id', $subjectId)->first();
    }

    private function excerpt(string $body): string
    {
        return Str::limit(trim($body), self::EXCERPT_LENGTH);
    }
}
