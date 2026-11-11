<?php

namespace App\Services;

use App\Integration\Events\MovieNightInviteAnswered;
use App\Integration\Events\MovieNightInviteSent;
use App\Models\MovieNight;
use App\Models\Participant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use MoviePlatform\Common\Auth\JwtUser;
use MoviePlatform\Common\Integration\EventPublisher;
use MoviePlatform\Common\MovieNights\MovieNightStatus;
use MoviePlatform\Common\MovieNights\ParticipantStatus;

class ParticipantService
{
    public function __construct(private EventPublisher $events) {}

    public function invite(JwtUser $host, string $nightId, array $userIds): array
    {
        [$night, $invited] = DB::transaction(function () use ($host, $nightId, $userIds) {
            $night = $this->lockNight($nightId, $host->id);

            if ($night->host_id !== $host->id) {
                abort(403, 'Only the host can invite people.');
            }

            $this->ensureOpen($night);

            $alreadyInvited = $night->participants()->pluck('user_id')->all();
            $invited = array_values(array_diff(array_unique(array_map('strtolower', $userIds)), $alreadyInvited));

            $now = Carbon::now();
            Participant::insert(array_map(fn (string $userId) => [
                'movie_night_id' => $night->id,
                'user_id' => $userId,
                'status' => ParticipantStatus::Pending->value,
                'created_at' => $now,
            ], $invited));

            return [$night, $invited];
        });

        foreach ($invited as $userId) {
            $this->events->publish(MovieNightInviteSent::ROUTING_KEY, MovieNightInviteSent::data($night, $host->name, $userId));
        }

        return $invited;
    }

    public function rsvp(JwtUser $user, string $nightId, ParticipantStatus $status): void
    {
        [$night, $changed] = DB::transaction(function () use ($user, $nightId, $status) {
            $night = $this->lockNight($nightId, $user->id);

            if ($night->host_id === $user->id) {
                abort(422, 'The host is always going.');
            }

            $this->ensureOpen($night);

            $changed = Participant::where('movie_night_id', $night->id)
                ->where('user_id', $user->id)
                ->where('status', '<>', $status->value)
                ->update(['status' => $status->value, 'responded_at' => Carbon::now()]) > 0;

            return [$night, $changed];
        });

        if ($changed) {
            $this->events->publish(MovieNightInviteAnswered::ROUTING_KEY, MovieNightInviteAnswered::data($night, $user->id, $user->name, $status));
        }
    }

    private function ensureOpen(MovieNight $night): void
    {
        if ($night->status !== MovieNightStatus::Open) {
            abort(422, 'This movie night is no longer open.');
        }
    }

    private function lockNight(string $nightId, string $userId): MovieNight
    {
        $night = MovieNight::withParticipant($userId)->lockForUpdate()->find($nightId);

        if ($night === null) {
            abort(404, 'Movie night not found.');
        }

        return $night;
    }
}
