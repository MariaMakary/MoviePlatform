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
}
