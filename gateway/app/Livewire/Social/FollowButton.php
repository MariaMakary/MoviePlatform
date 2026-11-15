<?php

namespace App\Livewire\Social;

use App\Exceptions\ServiceRequestException;
use App\Exceptions\ServiceUnavailable;
use App\Livewire\Concerns\RedirectsGuestsToLogin;
use App\Livewire\Concerns\WithServiceFallback;
use App\Services\AuthSession;
use App\Services\Clients\SocialClient;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Follow / Unfollow for one user. Hidden on the user's own profile; a guest is sent to log in.
 * Lists pass `following` from one bulk call; the button calls follow-stats itself only when nothing was passed.
 * Dispatches `follow-changed` (userId, following) so the counts on the page update.
 */
class FollowButton extends Component
{
    use RedirectsGuestsToLogin;
    use WithServiceFallback;

    #[Locked]
    public string $userId;

    #[Locked]
    public bool $compact = false;

    public ?bool $following = null;

    public ?string $error = null;

    public function render(AuthSession $auth, SocialClient $social): View
    {
        $me = $auth->user();

        if ($me !== null && $this->following === null && $me['id'] !== $this->userId) {
            $stats = $this->fromService('follow', fn () => $social->followStats($this->userId));
            $this->following = $stats['is_following'] ?? null;
        }

        return view('livewire.social.follow-button', [
            'loggedIn' => $me !== null,
            'self' => $me !== null && $me['id'] === $this->userId,
        ]);
    }

    public function toggle(SocialClient $social)
    {
        if (! app(AuthSession::class)->check()) {
            return $this->logIn();
        }

        $follow = ! $this->following;

        try {
            $follow ? $social->follow($this->userId) : $social->unfollow($this->userId);
        } catch (ValidationException) {
            // Already following (followed in another tab): the button was out of date.
        } catch (ServiceUnavailable) {
            $this->error = "Couldn't update it. Try again.";

            return null;
        } catch (ServiceRequestException $e) {
            if ($e->getStatusCode() === 401) {
                return $this->logIn();
            }

            throw $e;
        }

        $this->error = null;
        $this->following = $follow;
        $this->dispatch('follow-changed', userId: $this->userId, following: $follow);

        return null;
    }

    public function logIn()
    {
        return $this->redirectToLogin('Log in to follow people.');
    }
}
