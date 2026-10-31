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
                <x-movie-card wire:key="{{ $section }}-{{ $movie['id'] }}" :movie="$movie" :href="url('/movies/'.$movie['id'])" :show-release-date="$showReleaseDate" />
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
