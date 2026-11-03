<?php

namespace App\Services;

use App\Enums\MovieNightStatus;
use App\Enums\ParticipantStatus;
use App\Integration\Events\MovieNightInviteSent;
use App\Models\MovieNight;
use App\Models\Participant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use MoviePlatform\Common\Auth\JwtUser;
use MoviePlatform\Common\Integration\EventPublisher;

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

            if ($night->status !== MovieNightStatus::Open) {
                abort(422, 'This movie night is no longer open.');
            }

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

    private function lockNight(string $nightId, string $userId): MovieNight
    {
        $night = MovieNight::withParticipant($userId)->lockForUpdate()->find($nightId);

        if ($night === null) {
            abort(404, 'Movie night not found.');
        }

        return $night;
    }
}
