<?php

namespace App\Livewire\Engagement;

use App\Exceptions\ServiceRequestException;
use App\Exceptions\ServiceUnavailable;
use App\Livewire\Concerns\RedirectsGuestsToLogin;
use App\Livewire\Concerns\WithServiceFallback;
use App\Services\AuthSession;
use App\Services\Clients\EngagementClient;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

class AddToListButton extends Component
{
    use RedirectsGuestsToLogin;
    use WithServiceFallback;

    private const MAX_LISTS = 100;

    #[Locked]
    public string $movieId;

    public ?string $error = null;

    public function toggle(string $listId, bool $inList, AuthSession $auth, EngagementClient $engagement)
    {
        if (! $auth->check()) {
            return $this->logIn();
        }

        try {
            $inList ? $engagement->removeFromList($listId, $this->movieId) : $engagement->addToList($listId, $this->movieId);
        } catch (ServiceUnavailable) {
            $this->error = "Couldn't update the list. Try again.";

            return null;
        } catch (ValidationException) {
            $this->error = null;

            return null;
        } catch (ServiceRequestException $e) {
            if ($e->getStatusCode() === 401) {
                return $this->logIn();
            }

            $this->error = "Couldn't update the list. Try again.";

            return null;
        }

        $this->error = null;

        return null;
    }

    public function logIn()
    {
        return $this->redirectToLogin('Log in to add movies to your lists.');
    }

    public function render(AuthSession $auth, EngagementClient $engagement): View
    {
        $loggedIn = $auth->check();
        $lists = $loggedIn
            ? $this->fromService('lists', fn () => $engagement->lists(1, self::MAX_LISTS, $this->movieId)['data'], [])
            : [];

        return view('livewire.engagement.add-to-list-button', ['loggedIn' => $loggedIn, 'lists' => $lists]);
    }
}
