<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold tracking-tight text-white">My watchlist</h1>
        @if (! $this->isUnavailable('watchlist') && $total > 0)
            <p class="text-sm text-gray-400">{{ $total }} {{ Str::plural('movie', $total) }} to watch</p>
        @endif
    </div>

    @if ($error)
        <p role="alert" class="text-sm text-neon-rose">{{ $error }}</p>
    @endif

    @if ($this->isUnavailable('watchlist'))
        <x-service-unavailable :service="$unavailable['watchlist']" :retry="true" />
    @elseif ($total === 0)
        <x-empty-state title="Your watchlist is empty" description="Add movies you want to watch with the bookmark button and they will show up here.">
            <x-button :href="route('home')">Browse movies</x-button>
        </x-empty-state>
    @elseif ($this->isUnavailable('catalog'))
        <x-service-unavailable :service="$unavailable['catalog']" :retry="true" />
    @else
        {{-- One notice instead of one per card: the "Mark as watched" buttons wait until Engagement answers --}}
        @if ($watched === null)
            <x-service-unavailable :service="$unavailable['history']" :retry="true" compact />
        @endif

        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6">
            @foreach ($movies as $movie)
                <div wire:key="watchlist-{{ $movie['id'] }}" class="space-y-2">
                    <x-movie-card :movie="$movie" :href="route('movies.show', $movie['id'])" />
                    <x-button variant="ghost" size="sm" class="w-full"
                              wire:click="remove('{{ $movie['id'] }}')" wire:loading.attr="disabled" wire:target="remove('{{ $movie['id'] }}')">
                        Remove
                    </x-button>
                    @if ($watched !== null)
                        <livewire:engagement.mark-watched-button :movie-id="$movie['id']" :watched-at="$watched[$movie['id']] ?? null"
                                                                 :checked="true" :compact="true" :key="'watched-'.$movie['id']" />
                    @endif
                </div>
            @endforeach
        </div>

        @if ($lastPage > 1)
            <nav class="flex items-center justify-between text-sm text-gray-400" aria-label="Pagination">
                <x-button variant="ghost" size="sm" wire:click="goToPage({{ $page - 1 }})" :disabled="$page <= 1">Previous</x-button>
                <span>Page {{ $page }} of {{ $lastPage }}</span>
                <x-button variant="ghost" size="sm" wire:click="goToPage({{ $page + 1 }})" :disabled="$page >= $lastPage">Next</x-button>
            </nav>
        @endif
    @endif
</div>
