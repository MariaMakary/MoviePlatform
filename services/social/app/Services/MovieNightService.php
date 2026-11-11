<?php

namespace App\Services;

use App\Models\MovieNight;
use App\Models\Participant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use MoviePlatform\Common\Auth\JwtUser;
use MoviePlatform\Common\MovieNights\ParticipantStatus;

class MovieNightService
{
    public function create(JwtUser $host, array $data): MovieNight
    {
        $night = DB::transaction(function () use ($host, $data) {
            $night = MovieNight::create([
                'host_id' => $host->id,
                'title' => trim($data['title']),
                'description' => $this->optional($data['description'] ?? null),
                'scheduled_at' => Carbon::parse($data['scheduled_at'])->utc(),
                'location' => $this->optional($data['location'] ?? null),
                'meeting_link' => $this->optional($data['meeting_link'] ?? null),
            ]);

            $night->participants()->create([
                'user_id' => $host->id,
                'status' => ParticipantStatus::Accepted,
                'responded_at' => Carbon::now(),
            ]);

            return $night;
        });

        return $this->find($night->id, $host->id);
    }

    public function forUser(string $userId): Builder
    {
        return MovieNight::withParticipant($userId)
            ->addSelect(['my_status' => $this->statusOf($userId)])
            ->orderBy('scheduled_at')
            ->orderBy('id');
    }

    public function find(string $id, string $userId): MovieNight
    {
        $night = MovieNight::withParticipant($userId)
            ->addSelect(['my_status' => $this->statusOf($userId)])
            ->with([
                'participants' => fn ($query) => $query->orderBy('created_at')->orderBy('user_id'),
                'candidates' => fn ($query) => $query->withCount('votes')->orderBy('created_at')->orderBy('id'),
            ])
            ->withCount('votes')
            ->find($id);

        if ($night === null) {
            abort(404, 'Movie night not found.');
        }

        return $night;
    }

    private function statusOf(string $userId): Builder
    {
        return Participant::select('status')
            ->whereColumn('movie_night_id', 'movie_nights.id')
            ->where('user_id', $userId);
    }

    private function optional(?string $value): ?string
    {
        $value = trim($value ?? '');

        return $value === '' ? null : $value;
    }
}
