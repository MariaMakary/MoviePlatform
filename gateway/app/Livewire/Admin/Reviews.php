<?php

namespace App\Livewire\Admin;

use App\Exceptions\ServiceRequestException;
use App\Exceptions\ServiceUnavailable;
use App\Livewire\Concerns\WithServiceFallback;
use App\Services\Clients\CatalogClient;
use App\Services\Clients\EngagementClient;
use App\Services\Clients\IdentityClient;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * /admin/reviews: review moderation (1189), on Engagement's admin endpoints (1188). One Identity and one Catalog
 * bulk lookup per page for the authors' names and the movie titles.
 */
#[Title('Reviews · Admin')]
class Reviews extends Component
{
    use WithServiceFallback;

    private const PER_PAGE = 20;

    public const SENTIMENTS = ['negative' => 'Negative', 'neutral' => 'Neutral', 'positive' => 'Positive'];

    public const STATUSES = ['hidden' => 'Hidden by an admin', 'visible' => 'Not hidden'];

    private const ACTIONS = ['hide', 'unhide', 'delete'];

    private const MODAL = 'moderate-review';

    #[Url(except: '')]
    public string $sentiment = '';

    #[Url(except: '')]
    public string $movie = '';

    #[Url(except: '')]
    public string $status = '';

    #[Url(except: 1)]
    public int $page = 1;

    /** The action waiting for confirmation in the modal: hide, unhide or delete, on $pendingId. */
    #[Locked]
    public ?string $pendingAction = null;

    #[Locked]
    public ?string $pendingId = null;

    public ?string $notice = null;

    public ?string $error = null;

    // A new filter starts again from the first page.
    public function updated(string $property): void
    {
        if (in_array($property, ['sentiment', 'movie', 'status'], true)) {
            $this->page = 1;
        }
    }

    public function goToPage(int $page): void
    {
        $this->page = max(1, $page);
    }

    public function filterMovie(string $movieId): void
    {
        $this->movie = $movieId;
        $this->page = 1;
    }

    public function clearMovie(): void
    {
        $this->movie = '';
        $this->page = 1;
    }

    public function confirm(string $action, string $reviewId): void
    {
        if (! in_array($action, self::ACTIONS, true)) {
            return;
        }

        $this->pendingAction = $action;
        $this->pendingId = $reviewId;
        $this->dispatch('open-modal', name: self::MODAL);
    }

    public function apply(EngagementClient $engagement): void
    {
        [$action, $id] = [$this->pendingAction, $this->pendingId];

        if ($action === null || $id === null) {
            return;
        }

        $this->notice = $this->error = null;

        try {
            match ($action) {
                'hide' => $engagement->hideReview($id),
                'unhide' => $engagement->unhideReview($id),
                'delete' => $engagement->adminDeleteReview($id),
            };
            $this->notice = ['hide' => 'Review hidden.', 'unhide' => 'Review visible again.', 'delete' => 'Review deleted.'][$action];
        } catch (ServiceUnavailable $e) {
            $this->error = "{$e->service} isn't responding, so nothing changed. Try again in a moment.";
        } catch (ServiceRequestException $e) {
            if ($e->getStatusCode() !== 404) {
                throw $e;
            }

            $this->error = 'This review no longer exists.';
        }

        $this->pendingAction = $this->pendingId = null;
        $this->dispatch('close-modal', name: self::MODAL);
    }

    public function render(EngagementClient $engagement, IdentityClient $identity, CatalogClient $catalog): View
    {
        $this->page = max(1, $this->page);
        $filters = $this->filters();

        $result = $this->fromService('reviews', fn () => $engagement->adminReviews($filters, $this->page, self::PER_PAGE));
        $reviews = $result['data'] ?? [];
        $movieIds = array_values(array_unique([...array_column($reviews, 'movie_id'), ...($filters['movie_id'] ? [$filters['movie_id']] : [])]));

        return view('livewire.admin.reviews', [
            'reviews' => $reviews,
            'authors' => $this->lookup('authors', fn () => $identity->users(array_column($reviews, 'user_id'))),
            'movies' => $this->lookup('movies', fn () => $catalog->movies($movieIds)),
            'lastPage' => $result['meta']['last_page'] ?? 1,
            'total' => $result['meta']['total'] ?? 0,
            'filtered' => array_filter($filters, fn ($value) => $value !== null) !== [],
        ]);
    }

    /** Only known values reach Engagement: a hand-edited URL (?sentiment=angry) is treated as no filter. */
    private function filters(): array
    {
        return [
            'sentiment' => array_key_exists($this->sentiment, self::SENTIMENTS) ? $this->sentiment : null,
            'movie_id' => preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $this->movie) ? $this->movie : null,
            'hidden' => array_key_exists($this->status, self::STATUSES) ? $this->status === 'hidden' : null,
        ];
    }

    /** Id => item from a bulk lookup. Identity or Catalog down: the table still shows, without names or titles. */
    private function lookup(string $key, callable $call): array
    {
        if ($this->isUnavailable('reviews')) {
            return [];
        }

        try {
            return array_column($this->fromService($key, $call, []), null, 'id');
        } catch (ServiceRequestException) {
            return [];
        }
    }
}
