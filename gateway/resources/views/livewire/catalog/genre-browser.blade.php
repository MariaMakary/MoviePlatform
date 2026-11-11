<section id="browse-genres" aria-labelledby="browse-genres-title" class="space-y-4">
    <div>
        <h2 id="browse-genres-title" class="text-xl font-bold text-white sm:text-2xl">Browse by genre</h2>
        <p class="text-sm text-gray-400">Pick a genre to see its most popular movies, then scroll sideways for more.</p>
    </div>

    @if ($this->isUnavailable('genres'))
        <x-service-unavailable :service="$unavailable['genres']" :retry="true" />
    @elseif ($genres === [])
        <x-empty-state title="No genres yet" description="Genres appear once Catalog has synced them from TMDB." />
    @else
        <ul class="flex flex-wrap gap-2" aria-label="Genres">
            @foreach ($genres as $genre)
                @php($active = ($selected['id'] ?? null) === $genre['id'])
                <li wire:key="genre-chip-{{ $genre['id'] }}">
                    <button type="button" wire:click="select('{{ $genre['id'] }}')" aria-pressed="{{ $active ? 'true' : 'false' }}"
                            @class(['inline-flex rounded-full border px-4 py-2 text-sm font-medium transition focus-visible:ring-2 focus-visible:ring-cerulean focus-visible:outline-none',
                                    'border-cerulean bg-cerulean text-white' => $active,
                                    'border-white/15 bg-white/5 text-gray-100 hover:border-cerulean/60 hover:bg-cerulean/15 hover:text-white' => ! $active])>
                        {{ $genre['name'] }}
                    </button>
                </li>
            @endforeach
        </ul>

        @if ($selected)
            <div wire:loading.class="opacity-50" wire:target="select" class="space-y-4 transition">
                @if ($movies === [] && $this->isUnavailable('movies'))
                    <x-service-unavailable :service="$unavailable['movies']" :retry="true" />
                @elseif ($movies === [])
                    <x-empty-state title="No {{ $selected['name'] }} movies yet" description="Movies show up here once Catalog has them." />
                @else
                    <x-movie-row :label="$selected['name'].' movies'" :more="$this->hasMore() && ! $this->isUnavailable('movies') ? 'loadMore()' : null">
                        @foreach ($movies as $movie)
                            <x-movie-row.item wire:key="genre-row-{{ $movie['id'] }}">
                                <x-movie-card :movie="$movie" :href="route('movies.show', $movie['id'])" />
                            </x-movie-row.item>
                        @endforeach
                    </x-movie-row>

                    @if ($this->isUnavailable('movies'))
                        <div class="flex flex-wrap items-center gap-3">
                            <x-service-unavailable :service="$unavailable['movies']" compact />
                            <x-button variant="ghost" size="sm" wire:click="loadMore" wire:loading.attr="disabled" wire:target="loadMore">Try again</x-button>
                        </div>
                    @endif
                @endif
            </div>
        @endif
    @endif
</section>
