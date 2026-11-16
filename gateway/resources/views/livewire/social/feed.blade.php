<div class="mx-auto max-w-3xl space-y-6">
    <div>
        <h1 class="text-2xl font-bold tracking-tight text-white">Feed</h1>
        <p class="text-sm text-gray-400">What the people you follow rated, reviewed, watched and listed.</p>
    </div>

    @if ($this->isUnavailable('feed'))
        <x-service-unavailable :service="$unavailable['feed']" :retry="true" />
    @elseif ($entries === [])
        <x-empty-state title="Your feed is empty" description="Follow people to see what they rate, review and watch.">
            <x-button :href="route('people')">Find people</x-button>
        </x-empty-state>
    @else
        <ul class="space-y-3">
            @foreach ($entries as $entry)
                @php
                    $author = $authors[$entry['user_id']] ?? null;
                    $name = $author['name'] ?? 'KAIA member';
                    $movie = $entry['movie_id'] ? ($movies[$entry['movie_id']] ?? null) : null;
                    $movieTitle = $movie['title'] ?? 'a movie';
                @endphp
                <li wire:key="activity-{{ $entry['id'] }}" class="flex gap-4 rounded-2xl border border-white/10 bg-white/[0.02] p-4">
                    <a href="{{ route('users.show', $entry['user_id']) }}" class="shrink-0" aria-hidden="true" tabindex="-1">
                        <x-avatar :name="$name" :src="$author['avatar'] ?? null" size="md" />
                    </a>

                    <div class="min-w-0 flex-1 space-y-2">
                        <p class="text-sm text-gray-300">
                            <a href="{{ route('users.show', $entry['user_id']) }}" class="font-semibold text-white hover:underline">{{ $name }}</a>
                            @switch($entry['type'])
                                @case('rating')
                                    rated
                                    @break
                                @case('review')
                                    reviewed
                                    @break
                                @case('watched')
                                    watched
                                    @break
                                @case('list')
                                    created the list
                                    @break
                            @endswitch

                            @if ($entry['type'] === 'list')
                                <a href="{{ route('lists.show', $entry['subject_id']) }}" class="font-semibold text-sky-300 hover:underline">{{ $entry['data']['title'] ?? 'a list' }}</a>
                            @elseif ($entry['movie_id'])
                                <a href="{{ route('movies.show', $entry['movie_id']) }}" class="font-semibold text-sky-300 hover:underline">{{ $movieTitle }}</a>
                            @endif
                        </p>

                        @if ($entry['type'] === 'rating' && isset($entry['data']['score']))
                            <x-rating-stars :value="$entry['data']['score']" size="sm" />
                        @elseif ($entry['type'] === 'review' && isset($entry['data']['excerpt']))
                            <p class="text-sm leading-relaxed text-gray-200">“{{ $entry['data']['excerpt'] }}”</p>
                        @endif

                        <x-date :value="$entry['created_at']" format="j M Y, H:i" class="block text-xs text-gray-500" />
                    </div>

                    @if ($movie && $movie['poster_url'])
                        <a href="{{ route('movies.show', $entry['movie_id']) }}" class="shrink-0" aria-hidden="true" tabindex="-1">
                            <img src="{{ $movie['poster_url'] }}" alt="" loading="lazy" class="h-24 w-16 rounded-lg object-cover ring-1 ring-white/10">
                        </a>
                    @endif
                </li>
            @endforeach
        </ul>

        @if ($lastPage > 1)
            <nav class="flex items-center justify-between text-sm text-gray-400" aria-label="Pagination">
                <x-button variant="ghost" size="sm" wire:click="goToPage({{ $page - 1 }})" :disabled="$page <= 1">Previous</x-button>
                <span>Page {{ $page }} of {{ $lastPage }}</span>
                <x-button variant="ghost" size="sm" wire:click="goToPage({{ $page + 1 }})" :disabled="$page >= $lastPage">Next</x-button>
            </nav>
        @endif
    @endif
</div>
