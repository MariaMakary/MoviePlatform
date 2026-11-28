<x-admin.layout title="Dashboard">
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @include('livewire.admin.partials.stat-card', [
            'label' => 'Users',
            'value' => $users['total_users'] ?? 0,
            'details' => $users ? [number_format($users['admins']).' '.($users['admins'] === 1 ? 'admin' : 'admins'), number_format($users['blocked_users']).' blocked'] : [],
            'unavailable' => $unavailable['identity'] ?? null,
        ])
        @include('livewire.admin.partials.stat-card', [
            'label' => 'Reviews',
            'value' => $reviews['reviews'] ?? 0,
            'details' => [],
            'unavailable' => $unavailable['engagement'] ?? null,
        ])
        @include('livewire.admin.partials.stat-card', [
            'label' => 'Ratings',
            'value' => $reviews['ratings'] ?? 0,
            'details' => [],
            'unavailable' => $unavailable['engagement'] ?? null,
        ])
        @include('livewire.admin.partials.stat-card', [
            'label' => 'Movie nights',
            'value' => $community['movie_nights']['created'] ?? 0,
            'details' => $community ? [number_format($community['movie_nights']['finalized']).' finalized', number_format($community['messages_last_7_days']).' chat messages this week'] : [],
            'unavailable' => $unavailable['social'] ?? null,
        ])
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        @include('livewire.admin.partials.chart', [
            'id' => 'signups',
            'title' => 'Signups per day (last 30 days)',
            'chart' => $signupsChart ?? ['values' => []],
            'empty' => 'No signups in the last 30 days.',
            'unavailable' => $unavailable['identity'] ?? null,
        ])
        @include('livewire.admin.partials.chart', [
            'id' => 'activity',
            'title' => 'User activity per day (last 30 days)',
            'chart' => $activityChart ?? ['values' => []],
            'empty' => 'No ratings, reviews, watches or lists in the last 30 days.',
            'unavailable' => $unavailable['social'] ?? null,
        ])
        @include('livewire.admin.partials.chart', [
            'id' => 'sentiment',
            'title' => 'Review sentiment',
            'chart' => $sentimentChart ?? ['values' => []],
            'empty' => 'No reviews yet.',
            'unavailable' => $unavailable['engagement'] ?? null,
        ])
        @include('livewire.admin.partials.chart', [
            'id' => 'genres',
            'title' => 'Popular genres (watches and ratings)',
            'chart' => $genresChart,
            'empty' => 'No watches or ratings yet.',
            'unavailable' => $unavailable['engagement'] ?? $unavailable['genres'] ?? null,
        ])
    </div>

    <div class="grid gap-4 xl:grid-cols-3">
        @foreach ([
            ['id' => 'most-watched', 'title' => 'Most watched', 'rows' => $reviews['most_watched'] ?? [], 'value' => fn ($row) => number_format($row['watches']).' '.($row['watches'] === 1 ? 'watch' : 'watches')],
            ['id' => 'highest-rated', 'title' => 'Highest rated (3+ ratings)', 'rows' => $reviews['highest_rated'] ?? [], 'value' => fn ($row) => number_format($row['average'], 2).' ★ · '.number_format($row['ratings']).' ratings'],
        ] as $table)
            <section class="rounded-2xl border border-line bg-surface-1 p-5" aria-labelledby="{{ $table['id'] }}-title">
                <h2 id="{{ $table['id'] }}-title" class="text-sm font-semibold text-fg">{{ $table['title'] }}</h2>
                @if ($this->isUnavailable('engagement'))
                    <x-service-unavailable :service="$unavailable['engagement']" :retry="true" class="mt-3" />
                @elseif ($table['rows'] === [])
                    <p class="mt-3 text-sm text-muted">Nothing yet.</p>
                @else
                    @if ($this->isUnavailable('movies'))
                        <x-service-unavailable :service="$unavailable['movies']" :retry="true" compact class="mt-3" />
                    @endif
                    <ol class="mt-3 space-y-2">
                        @foreach ($table['rows'] as $row)
                            @php($movie = $movies[$row['movie_id']] ?? null)
                            <li wire:key="{{ $table['id'] }}-{{ $row['movie_id'] }}" class="flex items-center gap-3">
                                <span class="w-5 shrink-0 text-right text-xs text-subtle tabular-nums">{{ $loop->iteration }}</span>
                                @if ($movie['poster_url'] ?? null)
                                    <img src="{{ $movie['poster_url'] }}" alt="" class="h-12 w-8 shrink-0 rounded object-cover" loading="lazy">
                                @else
                                    <div class="h-12 w-8 shrink-0 rounded bg-surface-1" aria-hidden="true"></div>
                                @endif
                                <div class="min-w-0 flex-1">
                                    @if ($movie)
                                        <a href="{{ route('movies.show', $row['movie_id']) }}" class="block truncate text-sm font-medium text-fg hover:underline">{{ $movie['title'] }}</a>
                                    @else
                                        <span class="block truncate text-sm text-muted">{{ $this->isUnavailable('movies') ? 'Title unavailable' : 'Deleted movie' }}</span>
                                    @endif
                                    <span class="text-xs text-muted">{{ $table['value']($row) }}</span>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </section>
        @endforeach

        <section class="rounded-2xl border border-line bg-surface-1 p-5" aria-labelledby="most-active-title">
            <h2 id="most-active-title" class="text-sm font-semibold text-fg">Most active users</h2>
            @if ($this->isUnavailable('social'))
                <x-service-unavailable :service="$unavailable['social']" :retry="true" class="mt-3" />
            @elseif (($community['most_active_users'] ?? []) === [])
                <p class="mt-3 text-sm text-muted">Nothing yet.</p>
            @else
                @if ($this->isUnavailable('people'))
                    <x-service-unavailable :service="$unavailable['people']" :retry="true" compact class="mt-3" />
                @endif
                <ol class="mt-3 space-y-2">
                    @foreach ($community['most_active_users'] as $row)
                        @php($person = $people[$row['user_id']] ?? null)
                        <li wire:key="active-{{ $row['user_id'] }}" class="flex items-center gap-3">
                            <span class="w-5 shrink-0 text-right text-xs text-subtle tabular-nums">{{ $loop->iteration }}</span>
                            <x-avatar :name="$person['name'] ?? '?'" :src="$person['avatar'] ?? null" size="sm" />
                            <div class="min-w-0 flex-1">
                                @if ($person)
                                    <a href="{{ route('users.show', $row['user_id']) }}" class="block truncate text-sm font-medium text-fg hover:underline">{{ $person['name'] }}</a>
                                @else
                                    <span class="block truncate text-sm text-muted">{{ $this->isUnavailable('people') ? 'Name unavailable' : 'Deleted user' }}</span>
                                @endif
                                <span class="text-xs text-muted">{{ number_format($row['activities']) }} {{ $row['activities'] === 1 ? 'activity' : 'activities' }}</span>
                            </div>
                        </li>
                    @endforeach
                </ol>
            @endif
        </section>
    </div>
</x-admin.layout>
