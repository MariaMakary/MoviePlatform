<?php

namespace App\Livewire\Engagement;

use App\Exceptions\ServiceRequestException;
use App\Exceptions\ServiceUnavailable;
use App\Livewire\Concerns\WithServiceFallback;
use App\Services\AuthSession;
use App\Services\Clients\EngagementClient;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Bookmark toggle for one movie. Grids pass `in-watchlist` (loaded for the whole grid with one call);
 * without it, the button loads its own state (the movie page).
 */
class WatchlistButton extends Component
{
    use WithServiceFallback;

    #[Locked]
    public string $movieId;

    public ?bool $inWatchlist = null;

    #[Locked]
    public bool $compact = false;

    public ?string $error = null;

    public function render(AuthSession $auth, EngagementClient $engagement): View
    {
        $loggedIn = $auth->check();

        if ($loggedIn && $this->inWatchlist === null) {
            $ids = $this->fromService('watchlist', fn () => $engagement->watchlistContains([$this->movieId]));

            if ($ids !== null) {
                $this->inWatchlist = in_array($this->movieId, $ids, true);
            }
        }

        return view('livewire.engagement.watchlist-button', ['loggedIn' => $loggedIn]);
    }

    public function toggle(AuthSession $auth, EngagementClient $engagement)
    {
        if (! $auth->check()) {
            return $this->logIn();
        }

        if ($this->inWatchlist === null) {
            return null;
        }

        $add = ! $this->inWatchlist;

        try {
            $add ? $engagement->addToWatchlist($this->movieId) : $engagement->removeFromWatchlist($this->movieId);
        } catch (ServiceUnavailable) {
            $this->error = "Couldn't update your watchlist. Try again.";

            return null;
        } catch (ServiceRequestException $e) {
            if ($e->getStatusCode() === 401) {
                return $this->logIn();
            }

            throw $e;
        }

        $this->error = null;
        $this->inWatchlist = $add;
        $this->dispatch('watchlist-changed', movieId: $this->movieId, inWatchlist: $add);

        return null;
    }

    public function logIn()
    {
        // Livewire calls come from the page, so the browser's referer is the page to come back to.
        session()->put('url.intended', url()->previous(route('home')));
        session()->flash('info', 'Log in to add movies to your watchlist.');

        return $this->redirectRoute('login');
    }

    // Keeps every button of the same movie on the page in sync (a card and the movie header, for example).
    #[On('watchlist-changed')]
    public function syncState(string $movieId, bool $inWatchlist): void
    {
        if ($movieId === $this->movieId) {
            $this->inWatchlist = $inWatchlist;
            $this->error = null;
        }
    }
}
