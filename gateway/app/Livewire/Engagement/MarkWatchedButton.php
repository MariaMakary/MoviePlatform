<?php

namespace App\Livewire\Engagement;

use App\Exceptions\ServiceRequestException;
use App\Exceptions\ServiceUnavailable;
use App\Livewire\Concerns\RedirectsGuestsToLogin;
use App\Livewire\Concerns\WithServiceFallback;
use App\Services\AuthSession;
use App\Services\Clients\EngagementClient;
use App\Services\DisplayTime;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * "Mark as watched" for one movie, with a date picked in a modal. Grids pass `watched-at` and `checked`
 * (loaded for the whole grid with one call); without them, the button loads its own state (the movie page).
 */
class MarkWatchedButton extends Component
{
    use RedirectsGuestsToLogin;
    use WithServiceFallback;

    #[Locked]
    public string $movieId;

    /** Latest watched_at (UTC), null when never watched. */
    public ?string $watchedAt = null;

    public bool $checked = false;

    #[Locked]
    public bool $compact = false;

    /** The day picked in the modal, in the display timezone (Y-m-d). */
    public string $date = '';

    public ?string $error = null;

    public function mount(): void
    {
        $this->date = $this->today();
    }

    public function render(AuthSession $auth, EngagementClient $engagement): View
    {
        $loggedIn = $auth->check();

        if ($loggedIn && ! $this->checked) {
            $watched = $this->fromService('history', fn () => $engagement->historyContains([$this->movieId]));

            if ($watched !== null) {
                $this->watchedAt = $watched[$this->movieId] ?? null;
                $this->checked = true;
            }
        }

        return view('livewire.engagement.mark-watched-button', [
            'loggedIn' => $loggedIn,
            'modal' => 'mark-watched-'.$this->getId(),
            'today' => $this->today(),
        ]);
    }

    public function open()
    {
        if (! app(AuthSession::class)->check()) {
            return $this->logIn();
        }

        $this->resetValidation();
        $this->error = null;
        $this->date = $this->today();
        $this->dispatch('open-modal', name: 'mark-watched-'.$this->getId());

        return null;
    }

    public function save()
    {
        if (! app(AuthSession::class)->check()) {
            return $this->logIn();
        }

        $this->validate(
            ['date' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.$this->today()]],
            ['date.before_or_equal' => 'The date cannot be in the future.'],
        );

        try {
            $entry = app(EngagementClient::class)->markWatched($this->movieId, $this->watchedAtFor($this->date));
        } catch (ServiceUnavailable) {
            $this->error = "Couldn't save it. Try again.";

            return null;
        } catch (ServiceRequestException $e) {
            if ($e->getStatusCode() === 401) {
                return $this->logIn();
            }

            throw $e;
        }

        $this->error = null;
        $this->checked = true;
        $this->watchedAt = max($this->watchedAt ?? '', $entry['watched_at']);
        $this->dispatch('close-modal', name: 'mark-watched-'.$this->getId());
        $this->dispatch('movie-watched', movieId: $this->movieId);

        return null;
    }

    public function logIn()
    {
        return $this->redirectToLogin('Log in to keep track of what you watched.');
    }

    private function today(): string
    {
        return CarbonImmutable::now(DisplayTime::timezone())->toDateString();
    }

    // Today means now; an earlier day is stored at noon, so it shows the same day in any timezone nearby.
    private function watchedAtFor(string $date): ?string
    {
        return $date === $this->today() ? null : DisplayTime::toService($date.' 12:00');
    }
}
