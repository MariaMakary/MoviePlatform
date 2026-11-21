<?php

namespace App\Services\Clients;

use MoviePlatform\Common\MovieNights\ParticipantStatus;

class SocialClient extends BaseClient
{
    protected string $service = 'social';

    /** The current user's `{user_id, show_reviews, show_ratings, show_lists, show_history}`. */
    public function privacy(): array
    {
        return $this->get('/api/privacy')['data'];
    }

    /** Replaces the current user's settings: all four booleans, required. */
    public function updatePrivacy(array $settings): array
    {
        return $this->put('/api/privacy', $settings)['data'];
    }

    public function createMovieNight(array $data): array
    {
        return $this->post('/api/movie-nights', $data)['data'];
    }

    public function movieNights(int $page, int $perPage): array
    {
        return $this->get('/api/movie-nights', ['page' => $page, 'per_page' => $perPage]);
    }

    public function movieNight(string $id): array
    {
        return $this->get("/api/movie-nights/{$id}")['data'];
    }

    public function inviteToMovieNight(string $id, array $userIds): array
    {
        return $this->post("/api/movie-nights/{$id}/invites", ['user_ids' => array_values($userIds)])['data'];
    }

    public function answerInvitation(string $id, ParticipantStatus $status): array
    {
        return $this->patch("/api/movie-nights/{$id}/rsvp", ['status' => $status->value])['data'];
    }

    public function addCandidate(string $nightId, string $movieId): array
    {
        return $this->post("/api/movie-nights/{$nightId}/candidates", ['movie_id' => $movieId])['data'];
    }

    public function removeCandidate(string $nightId, string $candidateId): array
    {
        return $this->delete("/api/movie-nights/{$nightId}/candidates/".rawurlencode($candidateId))['data'];
    }

    public function vote(string $nightId, string $candidateId): array
    {
        return $this->post("/api/movie-nights/{$nightId}/votes", ['candidate_id' => $candidateId])['data'];
    }

    public function finalizeMovieNight(string $id, ?string $candidateId = null): array
    {
        return $this->post("/api/movie-nights/{$id}/finalize", array_filter(['candidate_id' => $candidateId]))['data'];
    }

    public function cancelMovieNight(string $id): array
    {
        return $this->post("/api/movie-nights/{$id}/cancel")['data'];
    }

    public function rooms(int $page, int $perPage): array
    {
        return $this->get('/api/rooms', ['page' => $page, 'per_page' => $perPage]);
    }

    public function roomForMovie(string $movieId): array
    {
        return $this->get('/api/rooms/by-movie/'.rawurlencode($movieId))['data'];
    }

    public function roomMessages(string $roomId, ?string $before = null): array
    {
        return $this->get('/api/rooms/'.rawurlencode($roomId).'/messages', array_filter(['before' => $before]));
    }

    public function sendMessage(string $roomId, string $body): array
    {
        return $this->post('/api/rooms/'.rawurlencode($roomId).'/messages', ['body' => $body])['data'];
    }

    public function follow(string $userId): array
    {
        return $this->post('/api/follow/'.rawurlencode($userId))['data'];
    }

    public function unfollow(string $userId): void
    {
        $this->delete('/api/follow/'.rawurlencode($userId));
    }

    /** `{data: [{user_id, followed_at}], meta}`, newest first. */
    /** What the people the current user follows did, newest first: {id, user_id, type, subject_id, movie_id, data, created_at}. */
    public function feed(int $page = 1, int $perPage = 20): array
    {
        return $this->get('/api/feed', ['page' => $page, 'per_page' => $perPage]);
    }

    public function followers(string $userId, int $page = 1, int $perPage = 20): array
    {
        return $this->get('/api/users/'.rawurlencode($userId).'/followers', ['page' => $page, 'per_page' => $perPage]);
    }

    /** Same shape as followers: `user_id` is the followed user. */
    public function following(string $userId, int $page = 1, int $perPage = 20): array
    {
        return $this->get('/api/users/'.rawurlencode($userId).'/following', ['page' => $page, 'per_page' => $perPage]);
    }

    /** `{followers, following, is_following}`; is_following is null for guests. */
    public function followStats(string $userId): array
    {
        return $this->get('/api/users/'.rawurlencode($userId).'/follow-stats')['data'];
    }

    /**
     * Whether the current user follows each id (max 100), in the same order.
     *
     * @param  string[]  $userIds
     * @return list<array{user_id: string, is_following: bool}>
     */
    public function followStatus(array $userIds): array
    {
        return $this->get('/api/follow/status', ['ids' => implode(',', $userIds)])['data'];
    }

    /** `{user_id, show_reviews, show_ratings, show_lists, show_history}`; all true for a user who never saved. */
    public function userPrivacy(string $userId): array
    {
        return $this->get('/api/users/'.rawurlencode($userId).'/privacy')['data'];
    }
}
