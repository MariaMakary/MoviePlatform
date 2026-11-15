<?php

namespace App\Services;

use App\Integration\Clients\CatalogClient;
use App\Integration\Events\MovieNightFinalized;
use App\Models\Candidate;
use App\Models\MovieNight;
use App\Models\Participant;
use App\Models\Vote;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use MoviePlatform\Common\Auth\JwtUser;
use MoviePlatform\Common\Integration\EventPublisher;
use MoviePlatform\Common\MovieNights\MovieNightStatus;
use MoviePlatform\Common\MovieNights\ParticipantStatus;

class MovieNightService
{
    public function __construct(private EventPublisher $events, private CatalogClient $catalog) {}

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
            ->addSelect([
                'my_status' => $this->statusOf($userId),
                'my_vote' => Vote::select('candidate_id')->whereColumn('movie_night_id', 'movie_nights.id')->where('user_id', $userId),
            ])
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

    public function lock(string $id, string $userId): MovieNight
    {
        $night = MovieNight::withParticipant($userId)->lockForUpdate()->find($id);

        if ($night === null) {
            abort(404, 'Movie night not found.');
        }

        return $night;
    }

    public function ensureOpen(MovieNight $night): void
    {
        if ($night->status !== MovieNightStatus::Open) {
            abort(422, 'This movie night is no longer open.');
        }
    }

    public function finalize(JwtUser $host, string $id, ?string $candidateId): void
    {
        [$night, $guestIds, $title] = DB::transaction(function () use ($host, $id, $candidateId) {
            $night = $this->lock($id, $host->id);

            if ($night->host_id !== $host->id) {
                abort(403, 'Only the host can finalize the movie night.');
            }

            $this->ensureOpen($night);

            $winner = $this->winner($night, $candidateId);
            $title = $this->catalog->movieTitle($winner->movie_id);

            if ($title === null) {
                abort(422, 'The winning movie is no longer in the catalog.');
            }

            $night->status = MovieNightStatus::Finalized;
            $night->winner_movie_id = $winner->movie_id;
            $night->save();

            $guestIds = $night->participants()
                ->where('status', ParticipantStatus::Accepted->value)
                ->where('user_id', '<>', $night->host_id)
                ->orderBy('created_at')
                ->orderBy('user_id')
                ->pluck('user_id')
                ->all();

            return [$night, $guestIds, $title];
        });

        $this->events->publish(MovieNightFinalized::ROUTING_KEY, MovieNightFinalized::data($night, $guestIds, $title));
    }

    public function cancel(JwtUser $host, string $id): void
    {
        DB::transaction(function () use ($host, $id) {
            $night = $this->lock($id, $host->id);

            if ($night->host_id !== $host->id) {
                abort(403, 'Only the host can cancel the movie night.');
            }

            $this->ensureOpen($night);

            $night->status = MovieNightStatus::Cancelled;
            $night->save();
        });
    }

    private function winner(MovieNight $night, ?string $candidateId): Candidate
    {
        $candidates = $night->candidates()->withCount('votes')->get();

        if ($candidates->isEmpty()) {
            abort(422, 'Add a movie to the shortlist first.');
        }

        $leaders = $candidates->where('votes_count', $candidates->max('votes_count'));

        if ($candidateId === null) {
            if ($leaders->count() > 1) {
                throw ValidationException::withMessages(['candidate_id' => 'It is a tie: choose one of the movies with the most votes.']);
            }

            return $leaders->first();
        }

        $chosen = $leaders->firstWhere('id', strtolower($candidateId));

        if ($chosen === null) {
            throw ValidationException::withMessages(['candidate_id' => 'Choose one of the movies with the most votes.']);
        }

        return $chosen;
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
