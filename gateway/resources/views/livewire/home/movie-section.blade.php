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
        @php($more = $this->hasMore($section) && ! $this->isUnavailable($section) ? "loadMore('{$section}')" : null)

        <x-movie-row :label="$title" :more="$more">
            @foreach ($movies[$section] as $movie)
                <x-movie-row.item wire:key="{{ $section }}-{{ $movie['id'] }}" class="relative">
                    <x-movie-card :movie="$movie" :href="route('movies.show', $movie['id'])" :show-release-date="$showReleaseDate" />

                    {{-- Logged in: only once its state is known (Engagement may be down, see the notice above) --}}
                    @if (! $loggedIn || array_key_exists($movie['id'], $inWatchlist))
                        <div class="absolute top-2 left-2">
                            <livewire:engagement.watchlist-button :movie-id="$movie['id']" :in-watchlist="$inWatchlist[$movie['id']] ?? null" :compact="true"
                                                                  :key="'watchlist-'.$section.'-'.$movie['id']" />
                        </div>
                    @endif
                </x-movie-row.item>
            @endforeach
        </x-movie-row>

        @if ($this->isUnavailable($section))
            <div class="flex flex-wrap items-center gap-3">
                <x-service-unavailable :service="$unavailable[$section]" compact />
                @if ($this->hasMore($section))
                    <x-button variant="ghost" size="sm" wire:click="loadMore('{{ $section }}')" wire:loading.attr="disabled" wire:target="loadMore('{{ $section }}')">Try again</x-button>
                @endif
            </div>
        @endif
    @endif
</section>
