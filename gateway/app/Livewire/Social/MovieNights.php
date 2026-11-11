<?php

namespace App\Livewire\Social;

use App\Livewire\Concerns\AnswersInvitations;
use App\Livewire\Concerns\WithServiceFallback;
use App\Services\AuthSession;
use App\Services\Clients\IdentityClient;
use App\Services\Clients\SocialClient;
use App\Services\DisplayTime;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use MoviePlatform\Common\MovieNights\MovieNightStatus;
use MoviePlatform\Common\MovieNights\ParticipantStatus;

#[Title('My movie nights')]
class MovieNights extends Component
{
    use AnswersInvitations;
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
        $userId = $auth->user()['id'] ?? null;

        $result = $this->fromService('nights', fn () => $social->movieNights($this->page, self::PER_PAGE));
        $nights = collect($result['data'] ?? []);

        $hosts = collect($this->fromService('hosts', fn () => $identity->users($nights->pluck('host_id')->unique()->values()->all()), []))
            ->keyBy('id');

        [$past, $coming] = $nights->partition(fn (array $night) => DisplayTime::fromService($night['scheduled_at'])->isPast());

        [$invitations, $upcoming] = $coming->partition(fn (array $night) => $night['host_id'] !== $userId
            && ParticipantStatus::tryFrom($night['my_status']) === ParticipantStatus::Pending
            && MovieNightStatus::tryFrom($night['status']) === MovieNightStatus::Open);

        return view('livewire.social.movie-nights', [
            'invitations' => $invitations->values(),
            'upcoming' => $upcoming->values(),
            'past' => $past->reverse()->values(),
            'hosts' => $hosts,
            'userId' => $userId,
            'lastPage' => $result['meta']['last_page'] ?? 1,
        ]);
    }

    protected function afterAnswerUrl(string $nightId): string
    {
        return route('movie-nights.index');
    }
}
