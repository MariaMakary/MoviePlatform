<?php

namespace App\Livewire\Engagement;

use App\Exceptions\ServiceRequestException;
use App\Exceptions\ServiceUnavailable;
use App\Livewire\Concerns\RedirectsGuestsToLogin;
use App\Livewire\Concerns\WithServiceFallback;
use App\Services\AuthSession;
use App\Services\Clients\EngagementClient;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/** Average rating of a movie, and 1–5 stars for the logged-in user's own score. */
class RateMovie extends Component
{
    use RedirectsGuestsToLogin;
    use WithServiceFallback;

    private const MAX_SCORE = 5;

    #[Locked]
    public string $movieId;

    public ?string $error = null;

    public function render(AuthSession $auth, EngagementClient $engagement): View
    {
        // Loaded on every render, so a new rating and "Try again" show the fresh average.
        $summary = $this->fromService('rating', fn () => $engagement->ratingSummary($this->movieId));

        return view('livewire.engagement.rate-movie', [
            'summary' => $summary,
            'loggedIn' => $auth->check(),
            'maxScore' => self::MAX_SCORE,
        ]);
    }

    public function rate(int $score)
    {
        if (! app(AuthSession::class)->check()) {
            return $this->logIn();
        }

        if ($score < 1 || $score > self::MAX_SCORE) {
            return null;
        }

        try {
            app(EngagementClient::class)->rate($this->movieId, $score);
        } catch (ServiceUnavailable) {
            $this->error = "Couldn't save your rating. Try again.";

            return null;
        } catch (ServiceRequestException $e) {
            if ($e->getStatusCode() === 401) {
                return $this->logIn();
            }

            throw $e;
        }

        $this->error = null;

        return null;
    }

    public function logIn()
    {
        return $this->redirectToLogin('Log in to rate movies.');
    }
}
