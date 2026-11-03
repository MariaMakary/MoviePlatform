<div class="space-y-8">
    <div>
        <h1 class="text-2xl font-bold tracking-tight text-white">History</h1>
        @if (! $this->isUnavailable('history') && $total > 0)
            <p class="text-sm text-gray-400">{{ $total }} {{ Str::plural('movie', $total) }} watched</p>
        @endif
    </div>

    @if ($this->isUnavailable('history'))
        <x-service-unavailable :service="$unavailable['history']" :retry="true" />
    @elseif ($total === 0)
        <x-empty-state title="Nothing watched yet" description="Use “Mark as watched” on a movie and it will show up here.">
            <x-button :href="route('watchlist')">Open my watchlist</x-button>
        </x-empty-state>
    @elseif ($this->isUnavailable('catalog'))
        <x-service-unavailable :service="$unavailable['catalog']" :retry="true" />
    @else
        @foreach ($months as $month => $items)
            <section wire:key="month-{{ $month }}" aria-labelledby="month-{{ $loop->index }}" class="space-y-4">
                <h2 id="month-{{ $loop->index }}" class="text-xl font-bold text-white">{{ $month }}</h2>

                <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6">
                    @foreach ($items as $item)
                        <div wire:key="history-{{ $item['entry']['id'] }}" class="space-y-1">
                            <x-movie-card :movie="$item['movie']" :href="route('movies.show', $item['movie']['id'])" />
                            <p class="px-0.5 text-xs text-gray-400">Watched <x-date :value="$item['entry']['watched_at']" format="j M Y" /></p>
                        </div>
                    @endforeach
                </div>
            </section>
        @endforeach

        @if ($lastPage > 1)
            <nav class="flex items-center justify-between text-sm text-gray-400" aria-label="Pagination">
                <x-button variant="ghost" size="sm" wire:click="goToPage({{ $page - 1 }})" :disabled="$page <= 1">Previous</x-button>
                <span>Page {{ $page }} of {{ $lastPage }}</span>
                <x-button variant="ghost" size="sm" wire:click="goToPage({{ $page + 1 }})" :disabled="$page >= $lastPage">Next</x-button>
            </nav>
        @endif
    @endif
</div>
