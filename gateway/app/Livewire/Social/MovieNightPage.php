<?php

namespace App\Livewire\Social;

use App\Exceptions\ServiceRequestException;
use App\Exceptions\ServiceUnavailable;
use App\Livewire\Concerns\AnswersInvitations;
use App\Livewire\Concerns\PicksUsers;
use App\Livewire\Concerns\WithServiceFallback;
use App\Services\AuthSession;
use App\Services\Clients\CatalogClient;
use App\Services\Clients\IdentityClient;
use App\Services\Clients\SocialClient;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use MoviePlatform\Common\MovieNights\MovieNightStatus;
use MoviePlatform\Common\MovieNights\ParticipantStatus;

class MovieNightPage extends Component
{
    use AnswersInvitations;
    use PicksUsers;
    use WithServiceFallback;

    #[Locked]
    public string $id;

    #[Locked]
    public array $participantIds = [];

    public ?string $winnerChoice = null;

    public function invite(SocialClient $social)
    {
        $this->resetErrorBag();
        unset($this->unavailable['invite']);

        if ($this->inviteeIds() === []) {
            $this->addError('invitees', 'Pick at least one person to invite.');

            return null;
        }

        $night = $this->fromService('invite', fn () => $social->inviteToMovieNight($this->id, $this->inviteeIds()));

        if ($night === null) {
            return null;
        }

        session()->flash('success', count($this->invitees) === 1 ? 'Invitation sent.' : 'Invitations sent.');

        return $this->redirectRoute('movie-nights.show', $this->id);
    }

    public function finalize(SocialClient $social)
    {
        return $this->changeNight(
            fn () => $social->finalizeMovieNight($this->id, $this->winnerChoice),
            'The movie night is finalized. Everyone going has been told.',
            "The movie night couldn't be finalized right now. Try again in a moment.",
        );
    }

    public function cancel(SocialClient $social)
    {
        return $this->changeNight(
            fn () => $social->cancelMovieNight($this->id),
            'The movie night is cancelled.',
            "The movie night couldn't be cancelled right now. Try again in a moment.",
        );
    }

    public function render(SocialClient $social, IdentityClient $identity, CatalogClient $catalog, AuthSession $auth): View
    {
        $night = $this->fromService('night', fn () => $social->movieNight($this->id));
        $userId = $auth->user()['id'] ?? null;

        $participants = collect($night['participants'] ?? []);
        $this->participantIds = $participants->pluck('user_id')->all();

        $people = $night === null ? collect()
            : collect($this->fromService('people', fn () => $identity->users($this->participantIds), []))->keyBy('id');

        $status = MovieNightStatus::tryFrom($night['status'] ?? '');
        $open = $status === MovieNightStatus::Open;
        $hosting = $night !== null && $night['host_id'] === $userId;
        $candidates = collect($night['candidates'] ?? []);
        $winnerMovieId = $night['winner_movie_id'] ?? null;

        return view('livewire.social.movie-night-page', [
            'night' => $night,
            'host' => $night === null ? null : $people->get($night['host_id']),
            'people' => $people,
            'participants' => $participants,
            'attendees' => $participants->filter(fn (array $participant) => $participant['status'] === ParticipantStatus::Accepted->value)->values(),
            'counts' => $participants->countBy(fn (array $participant) => $participant['status']),
            'userId' => $userId,
            'myStatus' => ParticipantStatus::tryFrom($night['my_status'] ?? ''),
            'open' => $open,
            'finalized' => $status === MovieNightStatus::Finalized,
            'cancelled' => $status === MovieNightStatus::Cancelled,
            'canInvite' => $open && $hosting,
            'canAnswer' => $open && ! $hosting && $night['my_status'] !== null,
            'canManage' => $open && $hosting,
            'winner' => $status === MovieNightStatus::Finalized && $winnerMovieId ? $this->winnerMovie($catalog, $winnerMovieId) : null,
            'winnerVotes' => $candidates->firstWhere('movie_id', $winnerMovieId)['votes'] ?? 0,
            'leaders' => $open && $hosting ? $this->leaders($catalog, $candidates) : [],
            'maxInvitees' => self::MAX_INVITEES,
        ])->title($night['title'] ?? 'Movie night');
    }

    protected function excludedUserIds(): array
    {
        return $this->participantIds;
    }

    protected function afterAnswerUrl(string $nightId): string
    {
        return route('movie-nights.show', $this->id);
    }

    private function changeNight(callable $call, string $done, string $down)
    {
        try {
            $call();
            session()->flash('success', $done);
        } catch (ServiceUnavailable) {
            session()->flash('error', $down);
        } catch (ValidationException $e) {
            session()->flash('error', collect($e->errors())->flatten()->first() ?? $down);
        } catch (ServiceRequestException $e) {
            session()->flash('error', $e->getMessage());
        }

        return $this->redirectRoute('movie-nights.show', $this->id);
    }

    private function winnerMovie(CatalogClient $catalog, string $movieId): ?array
    {
        return $this->fromService('winner', function () use ($catalog, $movieId) {
            try {
                return $catalog->movie($movieId);
            } catch (ServiceRequestException) {
                return null;
            }
        });
    }

    private function leaders(CatalogClient $catalog, Collection $candidates): array
    {
        if ($candidates->isEmpty()) {
            return [];
        }

        $top = $candidates->where('votes', $candidates->max('votes'))->values();
        $titles = collect($this->fromService('leaders', fn () => $catalog->movies($top->pluck('movie_id')->all()), []))->pluck('title', 'id');

        return $top->map(fn (array $candidate) => [
            'id' => $candidate['id'],
            'title' => $titles->get($candidate['movie_id'], 'Unknown movie'),
            'votes' => $candidate['votes'],
        ])->all();
    }
}
