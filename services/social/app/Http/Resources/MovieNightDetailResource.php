<?php

namespace App\Http\Resources;

use App\Models\Candidate;
use App\Models\Participant;

class MovieNightDetailResource extends MovieNightResource
{
    public function toArray($request): array
    {
        return [
            ...parent::toArray($request),
            'participants' => $this->participants->map(fn (Participant $participant) => [
                'user_id' => $participant->user_id,
                'status' => $participant->status->value,
                'is_host' => $participant->user_id === $this->host_id,
                'responded_at' => $participant->responded_at?->toIso8601ZuluString(),
            ])->all(),
            'candidates' => $this->candidates->map(fn (Candidate $candidate) => [
                'id' => $candidate->id,
                'movie_id' => $candidate->movie_id,
                'added_by' => $candidate->added_by,
                'votes' => $candidate->votes_count,
            ])->all(),
            'votes_cast' => $this->votes_count,
        ];
    }
}
