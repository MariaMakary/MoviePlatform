<section aria-labelledby="{{ $section }}-title" class="space-y-4">
    <div>
        <h2 id="{{ $section }}-title" class="text-xl font-bold text-white sm:text-2xl">{{ $title }}</h2>
        <p class="text-sm text-gray-400">{{ $subtitle }}</p>
    </div>

    @if ($movies[$section] === [] && $this->isUnavailable($section))
        <x-service-unavailable :service="$unavailable[$section]" :retry="true" />
    @elseif ($movies[$section] === [] && ! $this->hasMore($section))
        <x-empty-state title="No movies to show yet" description="Come back a bit later." />
    @else
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6">
            @foreach ($movies[$section] as $movie)
                <div wire:key="{{ $section }}-{{ $movie['id'] }}" class="relative">
                    <x-movie-card :movie="$movie" :href="route('movies.show', $movie['id'])" :show-release-date="$showReleaseDate" />

                    {{-- Logged in: only once its state is known (Engagement may be down, see the notice above) --}}
                    @if (! $loggedIn || array_key_exists($movie['id'], $inWatchlist))
                        <div class="absolute top-2 left-2">
                            <livewire:engagement.watchlist-button :movie-id="$movie['id']" :in-watchlist="$inWatchlist[$movie['id']] ?? null" :compact="true"
                                                                  :key="'watchlist-'.$section.'-'.$movie['id']" />
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        @if ($this->isUnavailable($section))
            <x-service-unavailable :service="$unavailable[$section]" />
        @endif

        @if ($this->hasMore($section))
            <div class="flex justify-center">
                <x-button variant="ghost" wire:click="loadMore('{{ $section }}')" wire:loading.attr="disabled" wire:target="loadMore('{{ $section }}')">
                    <span wire:loading.remove wire:target="loadMore('{{ $section }}')">Load more</span>
                    <span wire:loading wire:target="loadMore('{{ $section }}')">Loading…</span>
                </x-button>
            </div>
        @endif
    @endif
</section>
