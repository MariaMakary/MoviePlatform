<?php

namespace App\Livewire\Concerns;

use App\Exceptions\ServiceRequestException;
use App\Exceptions\ServiceUnavailable;
use App\Services\Clients\SocialClient;
use Illuminate\Validation\ValidationException;
use MoviePlatform\Common\MovieNights\ParticipantStatus;

trait AnswersInvitations
{
    public ?string $answerError = null;

    public function answer(string $nightId, string $status, SocialClient $social)
    {
        $answer = ParticipantStatus::tryFrom($status);

        if (! in_array($answer, [ParticipantStatus::Accepted, ParticipantStatus::Declined], true)) {
            return null;
        }

        try {
            $social->answerInvitation($nightId, $answer);
        } catch (ServiceUnavailable $e) {
            $this->answerError = "{$e->service} isn't responding, so your answer wasn't saved. Try again in a moment.";

            return null;
        } catch (ValidationException $e) {
            $this->answerError = collect($e->errors())->flatten()->first() ?? "Your answer couldn't be saved.";

            return null;
        } catch (ServiceRequestException $e) {
            $this->answerError = $e->getMessage();

            return null;
        }

        session()->flash('success', $answer === ParticipantStatus::Accepted ? "You're going. Enjoy the movie night!" : 'Invitation declined.');

        return $this->redirect($this->afterAnswerUrl($nightId));
    }

    abstract protected function afterAnswerUrl(string $nightId): string;
}
