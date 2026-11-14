<?php

namespace App\Livewire\Social;

use App\Livewire\Concerns\LoadsFollowStatus;
use App\Livewire\Concerns\WithServiceFallback;
use App\Services\Clients\IdentityClient;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Find people')]
class FindPeople extends Component
{
    use LoadsFollowStatus;
    use WithServiceFallback;

    #[Url(except: '')]
    public string $q = '';

    public function render(IdentityClient $identity): View
    {
        $query = mb_substr(trim($this->q), 0, 100);
        $searching = mb_strlen($query) >= 2;

        $users = $searching ? $this->fromService('identity', fn () => $identity->searchUsers($query), []) : [];

        return view('livewire.social.find-people', [
            'searching' => $searching,
            'users' => $users,
            'following' => $this->followStatus($users),
        ]);
    }
}
