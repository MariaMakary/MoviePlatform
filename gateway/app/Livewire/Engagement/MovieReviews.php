<?php

namespace App\Livewire\Engagement;

use App\Exceptions\ServiceRequestException;
use App\Exceptions\ServiceUnavailable;
use App\Livewire\Concerns\RedirectsGuestsToLogin;
use App\Livewire\Concerns\WithServiceFallback;
use App\Services\AuthSession;
use App\Services\Clients\EngagementClient;
use App\Services\Clients\IdentityClient;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/** The reviews section of the movie page: the list, and writing, editing and deleting your own review. */
class MovieReviews extends Component
{
    use RedirectsGuestsToLogin;
    use WithServiceFallback;

    private const PER_PAGE = 10;

    // Reviews of the current user scanned to find their review of this movie (Engagement has no per-movie lookup).
    private const OWN_REVIEWS_SCANNED = 100;

    private const BODY_RULES = ['required', 'string', 'min:10', 'max:2000', 'regex:/\S/'];

    #[Locked]
    public string $movieId;

    public int $page = 1;

    public string $body = '';

    public bool $editing = false;

    public string $editBody = '';

    public ?string $error = null;

    public function render(AuthSession $auth, EngagementClient $engagement, IdentityClient $identity): View
    {
        $this->page = max(1, $this->page);
        $user = $auth->user();

        $result = $this->fromService('reviews', fn () => $engagement->movieReviews($this->movieId, $this->page, self::PER_PAGE));
        $reviews = $result['data'] ?? [];
        $mine = $user && $result !== null ? $this->ownReview($engagement, $user['id']) : null;

        return view('livewire.engagement.movie-reviews', [
            'user' => $user,
            'reviews' => $reviews,
            'mine' => $mine,
            'authors' => $this->authors($identity, [...array_column($reviews, 'user_id'), ...($mine ? [$mine['user_id']] : [])]),
            'lastPage' => $result['meta']['last_page'] ?? 1,
            'total' => $result['meta']['total'] ?? 0,
        ]);
    }

    public function goToPage(int $page): void
    {
        $this->page = max(1, $page);
    }

    public function write()
    {
        if (! app(AuthSession::class)->check()) {
            return $this->logIn();
        }

        $this->validate(['body' => self::BODY_RULES], [], ['body' => 'review']);

        if ($this->callEngagement(fn (EngagementClient $engagement) => $engagement->createReview($this->movieId, trim($this->body)))) {
            $this->reset('body');
            $this->page = 1;
        }

        return null;
    }

    public function startEditing(AuthSession $auth, EngagementClient $engagement): void
    {
        $mine = $auth->user() ? $this->ownReview($engagement, $auth->user()['id']) : null;

        if ($mine === null) {
            return;
        }

        $this->resetValidation();
        $this->editing = true;
        $this->editBody = $mine['body'];
    }

    public function cancelEditing(): void
    {
        $this->resetValidation();
        $this->editing = false;
    }

    public function saveEdit(string $reviewId)
    {
        $this->validate(['editBody' => self::BODY_RULES], [], ['editBody' => 'review']);

        if ($this->callEngagement(fn (EngagementClient $engagement) => $engagement->updateReview($reviewId, trim($this->editBody)))) {
            $this->editing = false;
        }

        return null;
    }

    public function deleteReview(string $reviewId): void
    {
        if ($this->callEngagement(fn (EngagementClient $engagement) => $engagement->deleteReview($reviewId))) {
            $this->editing = false;
        }
    }

    public function logIn()
    {
        return $this->redirectToLogin('Log in to write a review.');
    }

    /** Runs a write; Engagement's 422 shows under the form, a down service as a short message. */
    private function callEngagement(callable $call): bool
    {
        try {
            $call(app(EngagementClient::class));
        } catch (ServiceUnavailable) {
            $this->error = "Couldn't save it, Engagement isn't responding. Try again.";

            return false;
        } catch (ServiceRequestException $e) {
            if ($e->getStatusCode() === 401) {
                $this->logIn();

                return false;
            }

            throw $e;
        }

        $this->error = null;

        return true;
    }

    private function ownReview(EngagementClient $engagement, string $userId): ?array
    {
        $own = $this->fromService('reviews', fn () => $engagement->userReviews($userId, 1, self::OWN_REVIEWS_SCANNED), []);

        return collect($own['data'] ?? [])->firstWhere('movie_id', $this->movieId);
    }

    /**
     * Author id => {name, avatar}, with one Identity call. Identity down, or its lookup (task 1145) not there yet:
     * the reviews still show, without names.
     */
    private function authors(IdentityClient $identity, array $ids): array
    {
        try {
            $users = $this->fromService('authors', fn () => $identity->users($ids), []);
        } catch (ServiceRequestException) {
            $users = [];
        }

        return array_column($users, null, 'id');
    }
}
