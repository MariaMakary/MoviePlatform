<?php

namespace App\Livewire\Social;

use App\Livewire\Concerns\LoadsFollowStatus;
use App\Livewire\Concerns\WithServiceFallback;
use App\Services\Clients\IdentityClient;
use App\Services\Clients\SocialClient;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/** /users/{id}/followers and /users/{id}/following: one Social page of ids, then one Identity call for the names. */
class FollowList extends Component
{
    use LoadsFollowStatus;
    use WithServiceFallback;

    private const PER_PAGE = 20;

    #[Locked]
    public string $id;

    /** followers | following, from the route name. */
    #[Locked]
    public string $type;

    #[Url(except: 1)]
    public int $page = 1;

    public function mount(string $id): void
    {
        $this->id = $id;
        $this->type = request()->routeIs('users.following') ? 'following' : 'followers';
    }

    public function goToPage(int $page): void
    {
        $this->page = max(1, $page);
    }

    public function render(SocialClient $social, IdentityClient $identity): View
    {
        $this->page = max(1, $this->page);

        $owner = $this->fromService('owner', fn () => $identity->users([$this->id])[0] ?? null);
        abort_if($owner === null && ! $this->isUnavailable('owner'), 404);

        $result = $this->fromService('social', fn () => $this->type === 'following'
            ? $social->following($this->id, $this->page, self::PER_PAGE)
            : $social->followers($this->id, $this->page, self::PER_PAGE));

        $ids = array_column($result['data'] ?? [], 'user_id');
        $users = $ids === [] ? [] : $this->fromService('identity', fn () => $identity->users($ids), []);

        $name = $owner['name'] ?? 'User';

        return view('livewire.social.follow-list', [
            'owner' => $owner,
            'users' => $users,
            'following' => $this->followStatus($users),
            'lastPage' => $result['meta']['last_page'] ?? 1,
        ])->title($this->type === 'following' ? "People {$name} follows" : "{$name}'s followers");
    }
}
