<?php

namespace App\Livewire\Social;

use App\Exceptions\ServiceRequestException;
use App\Exceptions\ServiceUnavailable;
use App\Livewire\Concerns\PicksUsers;
use App\Livewire\Concerns\WithServiceFallback;
use App\Services\Clients\SocialClient;
use App\Services\DisplayTime;
use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('New movie night')]
class CreateMovieNight extends Component
{
    use PicksUsers;
    use WithServiceFallback;

    public string $title = '';

    public string $description = '';

    public string $scheduled_at = '';

    public string $location = '';

    public string $meeting_link = '';

    public function save(SocialClient $social)
    {
        $this->resetErrorBag();
        unset($this->unavailable['save']);

        try {
            $scheduledAt = DisplayTime::toService($this->scheduled_at);
        } catch (InvalidFormatException) {
            $this->addError('scheduled_at', 'Pick a valid date and time.');

            return null;
        }

        $night = $this->fromService('save', fn () => $social->createMovieNight([
            'title' => $this->title,
            'description' => $this->description,
            'scheduled_at' => $scheduledAt,
            'location' => $this->location,
            'meeting_link' => $this->meeting_link,
        ]));

        if ($night === null) {
            return null;
        }

        if ($this->inviteeIds() !== []) {
            try {
                $social->inviteToMovieNight($night['id'], $this->inviteeIds());
            } catch (ServiceUnavailable|ServiceRequestException|ValidationException) {
                session()->flash('error', 'The movie night was created, but the invitations could not be sent. Invite people again below.');

                return $this->redirectRoute('movie-nights.show', $night['id']);
            }
        }

        session()->flash('success', 'Movie night created.');

        return $this->redirectRoute('movie-nights.show', $night['id']);
    }

    public function render(): View
    {
        return view('livewire.social.create-movie-night', [
            'minDate' => CarbonImmutable::now(DisplayTime::timezone())->format('Y-m-d\TH:i'),
            'maxInvitees' => self::MAX_INVITEES,
        ]);
    }
}
