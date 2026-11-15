<?php

namespace App\Services;

use App\Events\VotesUpdated;
use App\Models\Candidate;
use App\Models\MovieNight;
use App\Models\Participant;
use App\Models\Vote;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use MoviePlatform\Common\Auth\JwtUser;
use MoviePlatform\Common\MovieNights\MovieNightStatus;
use MoviePlatform\Common\MovieNights\ParticipantStatus;
use Throwable;

class VotingService
{
    public function __construct(private MovieNightService $movieNights) {}

    public function addCandidate(JwtUser $user, string $nightId, string $movieId): void
    {
        DB::transaction(function () use ($user, $nightId, $movieId) {
            $night = $this->movieNights->lock($nightId, $user->id);
            $this->ensureAccepted($night, $user->id);
            $this->movieNights->ensureOpen($night);

            $movieId = strtolower($movieId);

            if ($night->candidates()->where('movie_id', $movieId)->exists()) {
                throw ValidationException::withMessages(['movie_id' => 'This movie is already on the shortlist.']);
            }

            $night->candidates()->create(['movie_id' => $movieId, 'added_by' => $user->id]);
        });

        $this->broadcastTotals($nightId);
    }

    public function removeCandidate(JwtUser $user, string $nightId, string $candidateId): void
    {
        DB::transaction(function () use ($user, $nightId, $candidateId) {
            $night = $this->movieNights->lock($nightId, $user->id);
            $candidate = $night->candidates()->find($candidateId);

            if ($candidate === null) {
                abort(404, 'Candidate not found.');
            }

            if ($candidate->added_by !== $user->id && $night->host_id !== $user->id) {
                abort(403, 'Only the host or the person who added this movie can remove it.');
            }

            $this->movieNights->ensureOpen($night);

            $candidate->delete();
        });

        $this->broadcastTotals($nightId);
    }

    public function vote(JwtUser $user, string $nightId, string $candidateId): void
    {
        $changed = DB::transaction(function () use ($user, $nightId, $candidateId) {
            $night = $this->movieNights->lock($nightId, $user->id);
            $this->ensureAccepted($night, $user->id);
            $this->movieNights->ensureOpen($night);

            $candidateId = strtolower($candidateId);

            if (! $night->candidates()->whereKey($candidateId)->exists()) {
                throw ValidationException::withMessages(['candidate_id' => 'This movie is not on the shortlist of this movie night.']);
            }

            $current = Vote::where('movie_night_id', $night->id)->where('user_id', $user->id)->value('candidate_id');

            if ($current === $candidateId) {
                return false;
            }

            if ($current === null) {
                Vote::insert([
                    'movie_night_id' => $night->id,
                    'user_id' => $user->id,
                    'candidate_id' => $candidateId,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]);
            } else {
                Vote::where('movie_night_id', $night->id)
                    ->where('user_id', $user->id)
                    ->update(['candidate_id' => $candidateId, 'updated_at' => Carbon::now()]);
            }

            return true;
        });

        if ($changed) {
            $this->broadcastTotals($nightId);
        }
    }

    public function removeMovie(string $movieId): void
    {
        $nightIds = MovieNight::where('status', MovieNightStatus::Open->value)
            ->whereIn('id', Candidate::select('movie_night_id')->where('movie_id', $movieId))
            ->orderBy('id')
            ->lockForUpdate()
            ->pluck('id')
            ->all();

        if ($nightIds === []) {
            return;
        }

        Candidate::where('movie_id', $movieId)->whereIn('movie_night_id', $nightIds)->delete();

        DB::afterCommit(function () use ($nightIds) {
            foreach ($nightIds as $nightId) {
                $this->broadcastTotals($nightId);
            }
        });
    }

    public function totals(string $nightId): array
    {
        return Candidate::where('movie_night_id', $nightId)
            ->withCount('votes')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->map(fn (Candidate $candidate) => ['candidate_id' => $candidate->id, 'votes' => $candidate->votes_count])
            ->all();
    }

    private function ensureAccepted(MovieNight $night, string $userId): void
    {
        $status = Participant::where('movie_night_id', $night->id)->where('user_id', $userId)->value('status');

        if ($status !== ParticipantStatus::Accepted) {
            abort(403, 'Accept the invitation first.');
        }
    }

    private function broadcastTotals(string $nightId): void
    {
        try {
            event(new VotesUpdated($nightId, $this->totals($nightId)));
        } catch (Throwable $e) {
            Log::warning('VotesUpdated was not broadcast', ['movie_night_id' => $nightId, 'error' => $e->getMessage()]);
        }
    }
}
