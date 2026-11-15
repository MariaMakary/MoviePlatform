<?php

namespace App\Livewire\Engagement;

use App\Exceptions\ServiceRequestException;
use App\Livewire\Concerns\WithServiceFallback;
use App\Services\AuthSession;
use App\Services\Clients\CatalogClient;
use App\Services\Clients\EngagementClient;
use App\Services\Clients\IdentityClient;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/** /lists/browse: everyone's public lists, with one Identity and one Catalog call per page. */
#[Title('Browse lists')]
class BrowseLists extends Component
{
    use WithServiceFallback;

    private const PER_PAGE = 20;

    private const MAX_SEARCH = 150;

    #[Url(except: '')]
    public string $q = '';

    #[Url(except: 1)]
    public int $page = 1;

    // A new search starts again from the first page.
    public function updatedQ(): void
    {
        $this->page = 1;
    }

    public function goToPage(int $page): void
    {
        $this->page = max(1, $page);
    }

    public function render(EngagementClient $engagement, IdentityClient $identity, CatalogClient $catalog, AuthSession $auth): View
    {
        $this->page = max(1, $this->page);
        $search = Str::limit(trim($this->q), self::MAX_SEARCH, '');

        $result = $this->fromService('lists', fn () => $engagement->publicLists($search, $this->page, self::PER_PAGE));
        $lists = $result['data'] ?? [];

        return view('livewire.engagement.browse-lists', [
            'lists' => $lists,
            'owners' => $this->owners($identity, array_column($lists, 'user_id')),
            'posters' => $this->posters($catalog, array_merge(...array_column($lists, 'first_movie_ids') ?: [[]])),
            'lastPage' => $result['meta']['last_page'] ?? 1,
            'total' => $result['meta']['total'] ?? 0,
            'loggedIn' => $auth->check(),
        ]);
    }

    /** Owner id => {name, avatar}. Identity down, or its lookup missing: the cards still show, without names. */
    private function owners(IdentityClient $identity, array $ids): array
    {
        try {
            $users = $this->fromService('owners', fn () => $identity->users($ids), []);
        } catch (ServiceRequestException) {
            $users = [];
        }

        return array_column($users, null, 'id');
    }

    /** Movie id => poster URL, for every collage of the page. Catalog down: the cards still show, without posters. */
    private function posters(CatalogClient $catalog, array $movieIds): array
    {
        $cards = $this->fromService('posters', fn () => $catalog->movies(array_values(array_unique($movieIds))), []);

        return array_column($cards, 'poster_url', 'id');
    }
}
