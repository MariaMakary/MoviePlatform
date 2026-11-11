<section id="browse-genres" aria-labelledby="browse-genres-title" class="space-y-4">
    <div>
        <h2 id="browse-genres-title" class="text-xl font-bold text-white sm:text-2xl">Browse by genre</h2>
        <p class="text-sm text-gray-400">Pick a genre to see its most popular movies.</p>
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
            <div wire:loading.class="opacity-50" wire:target="select" class="space-y-4 rounded-2xl border border-white/10 bg-white/[0.02] p-4 transition">
                @if ($this->isUnavailable('movies'))
                    <x-service-unavailable :service="$unavailable['movies']" :retry="true" />
                @elseif ($movies === [])
                    <x-empty-state title="No {{ $selected['name'] }} movies yet" description="Movies show up here once Catalog has them." />
                @else
                    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6">
                        @foreach ($movies as $movie)
                            <x-movie-card wire:key="genre-row-{{ $movie['id'] }}" :movie="$movie" :href="route('movies.show', $movie['id'])" />
                        @endforeach
                    </div>

                    <div class="flex justify-end">
                        <x-button variant="ghost" size="sm" :href="route('genres.show', $selected['id'])">
                            See all {{ number_format($total) }} {{ $selected['name'] }} movies &rarr;
                        </x-button>
                    </div>
                @endif
            </div>
        @endif
    @endif
</section>
