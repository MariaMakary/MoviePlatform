<?php

namespace App\Livewire\Social;

use App\Livewire\Concerns\PicksUsers;
use App\Livewire\Concerns\WithServiceFallback;
use App\Services\AuthSession;
use App\Services\Clients\IdentityClient;
use App\Services\Clients\SocialClient;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use MoviePlatform\Common\MovieNights\MovieNightStatus;

class MovieNightPage extends Component
{
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

        $this->participantIds = array_column($night['participants'] ?? [], 'user_id');

        $host = $night === null ? null
            : collect($this->fromService('host', fn () => $identity->users([$night['host_id']]), []))->first();

        return view('livewire.social.movie-night-page', [
            'night' => $night,
            'host' => $host,
            'userId' => $userId,
            'canInvite' => $night !== null
                && $night['host_id'] === $userId
                && MovieNightStatus::tryFrom($night['status']) === MovieNightStatus::Open,
            'maxInvitees' => self::MAX_INVITEES,
        ])->title($night['title'] ?? 'Movie night');
    }

    protected function excludedUserIds(): array
    {
        return $this->participantIds;
    }
}
