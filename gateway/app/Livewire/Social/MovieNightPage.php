<?php

namespace App\Livewire\Social;

use App\Livewire\Concerns\AnswersInvitations;
use App\Livewire\Concerns\PicksUsers;
use App\Livewire\Concerns\WithServiceFallback;
use App\Services\AuthSession;
use App\Services\Clients\IdentityClient;
use App\Services\Clients\SocialClient;
use Illuminate\Contracts\View\View;
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

    public function render(SocialClient $social, IdentityClient $identity, AuthSession $auth): View
    {
        $night = $this->fromService('night', fn () => $social->movieNight($this->id));
        $userId = $auth->user()['id'] ?? null;

        $participants = collect($night['participants'] ?? []);
        $this->participantIds = $participants->pluck('user_id')->all();

        $people = $night === null ? collect()
            : collect($this->fromService('people', fn () => $identity->users($this->participantIds), []))->keyBy('id');

        $open = $night !== null && MovieNightStatus::tryFrom($night['status']) === MovieNightStatus::Open;
        $hosting = $night !== null && $night['host_id'] === $userId;

        return view('livewire.social.movie-night-page', [
            'night' => $night,
            'host' => $night === null ? null : $people->get($night['host_id']),
            'people' => $people,
            'participants' => $participants,
            'counts' => $participants->countBy(fn (array $participant) => $participant['status']),
            'userId' => $userId,
            'myStatus' => ParticipantStatus::tryFrom($night['my_status'] ?? ''),
            'canInvite' => $open && $hosting,
            'canAnswer' => $open && ! $hosting && $night['my_status'] !== null,
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
}
