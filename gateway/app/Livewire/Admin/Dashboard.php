<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\WithServiceFallback;
use App\Services\Clients\CatalogClient;
use App\Services\Clients\EngagementClient;
use App\Services\Clients\IdentityClient;
use App\Services\Clients\SocialClient;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Admin')]
class Dashboard extends Component
{
    use WithServiceFallback;

    private const SENTIMENTS = ['positive' => 'Positive', 'neutral' => 'Neutral', 'negative' => 'Negative', 'not_analyzed' => 'Not analyzed'];

    private const TOP_GENRES = 10;

    public function render(IdentityClient $identity, EngagementClient $engagement, SocialClient $social, CatalogClient $catalog): View
    {
        $users = $this->fromService('identity', fn () => $identity->stats());
        $reviews = $this->fromService('engagement', fn () => $engagement->stats());
        $community = $this->fromService('social', fn () => $social->stats());

        $movieCounts = $reviews['movie_counts'] ?? [];
        $genres = $reviews === null ? [] : $this->fromService('genres', fn () => $movieCounts === [] ? [] : $catalog->genreCounts($movieCounts), []);

        $movieIds = collect([...($reviews['most_watched'] ?? []), ...($reviews['highest_rated'] ?? [])])->pluck('movie_id')->unique()->values()->all();
        $movies = $movieIds === [] ? [] : array_column($this->fromService('movies', fn () => $catalog->movies($movieIds), []), null, 'id');

        $userIds = collect($community['most_active_users'] ?? [])->pluck('user_id')->all();
        $people = $userIds === [] ? [] : array_column($this->fromService('people', fn () => $identity->users($userIds), []), null, 'id');

        return view('livewire.admin.dashboard', [
            'users' => $users,
            'reviews' => $reviews,
            'community' => $community,
            'movies' => $movies,
            'people' => $people,
            'signupsChart' => $users === null ? null : $this->perDayChart($users['signups'], 'Signups'),
            'activityChart' => $community === null ? null : $this->perDayChart($community['activity_per_day'], 'Activities'),
            'sentimentChart' => $reviews === null ? null : [
                'type' => 'doughnut',
                'labels' => array_values(self::SENTIMENTS),
                'values' => array_map(fn (string $key) => (int) ($reviews['sentiment'][$key] ?? 0), array_keys(self::SENTIMENTS)),
            ],
            'genresChart' => [
                'type' => 'bar',
                'label' => 'Watches and ratings',
                'labels' => array_column(array_slice($genres, 0, self::TOP_GENRES), 'genre'),
                'values' => array_column(array_slice($genres, 0, self::TOP_GENRES), 'count'),
            ],
        ]);
    }

    private function perDayChart(array $days, string $label): array
    {
        return [
            'type' => 'line',
            'label' => $label,
            'labels' => array_map(fn (array $day) => Carbon::parse($day['date'])->format('j M'), $days),
            'values' => array_map(fn (array $day) => (int) $day['count'], $days),
        ];
    }
}
