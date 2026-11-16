<?php

namespace App\Livewire\Social;

use App\Exceptions\ServiceRequestException;
use App\Livewire\Concerns\WithServiceFallback;
use App\Services\Clients\CatalogClient;
use App\Services\Clients\IdentityClient;
use App\Services\Clients\SocialClient;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/** /feed: what the people you follow rated, reviewed, watched and listed. One Identity and one Catalog call per page. */
#[Title('Feed')]
class Feed extends Component
{
    use WithServiceFallback;

    private const PER_PAGE = 20;

    #[Url(except: 1)]
    public int $page = 1;

    public function goToPage(int $page): void
    {
        $this->page = max(1, $page);
    }

    public function render(SocialClient $social, IdentityClient $identity, CatalogClient $catalog): View
    {
        $this->page = max(1, $this->page);
        $result = $this->fromService('feed', fn () => $social->feed($this->page, self::PER_PAGE));
        $entries = $result['data'] ?? [];

        return view('livewire.social.feed', [
            'entries' => $entries,
            'authors' => $this->authors($identity, array_column($entries, 'user_id')),
            'movies' => $this->movies($catalog, array_values(array_filter(array_column($entries, 'movie_id')))),
            'lastPage' => $result['meta']['last_page'] ?? 1,
        ]);
    }

    /** Author id => {name, avatar}. Identity down: the entries still show, without names. */
    private function authors(IdentityClient $identity, array $ids): array
    {
        try {
            $users = $this->fromService('authors', fn () => $identity->users($ids), []);
        } catch (ServiceRequestException) {
            $users = [];
        }

        return array_column($users, null, 'id');
    }

    /** Movie id => card. Catalog down: the entries still show, without titles and posters. */
    private function movies(CatalogClient $catalog, array $ids): array
    {
        return array_column($this->fromService('movies', fn () => $catalog->movies(array_values(array_unique($ids))), []), null, 'id');
    }
}
