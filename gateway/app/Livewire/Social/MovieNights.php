<?php

namespace App\Livewire\Social;

use App\Livewire\Concerns\WithServiceFallback;
use App\Services\AuthSession;
use App\Services\Clients\IdentityClient;
use App\Services\Clients\SocialClient;
use App\Services\DisplayTime;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('My movie nights')]
class MovieNights extends Component
{
    use WithServiceFallback;

    private const PER_PAGE = 100;

    #[Url(except: 1)]
    public int $page = 1;

    public function goToPage(int $page): void
    {
        $this->page = max(1, $page);
    }

    public function render(SocialClient $social, IdentityClient $identity, AuthSession $auth): View
    {
        $this->page = max(1, $this->page);

        $result = $this->fromService('nights', fn () => $social->movieNights($this->page, self::PER_PAGE));
        $nights = collect($result['data'] ?? []);

        $hosts = collect($this->fromService('hosts', fn () => $identity->users($nights->pluck('host_id')->unique()->values()->all()), []))
            ->keyBy('id');

        [$past, $upcoming] = $nights->partition(fn (array $night) => DisplayTime::fromService($night['scheduled_at'])->isPast());

        return view('livewire.social.movie-nights', [
            'upcoming' => $upcoming->values(),
            'past' => $past->reverse()->values(),
            'hosts' => $hosts,
            'userId' => $auth->user()['id'] ?? null,
            'lastPage' => $result['meta']['last_page'] ?? 1,
        ]);
    }
}
