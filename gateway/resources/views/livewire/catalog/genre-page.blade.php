<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div class="space-y-1">
            <a href="{{ route('home') }}#browse-genres" class="text-sm text-gray-400 hover:text-white hover:underline">&larr; All genres</a>
            <h1 class="text-2xl font-bold tracking-tight text-white">{{ $name }}</h1>
            @if (! $this->isUnavailable('movies') && $total > 0)
                <p class="text-sm text-gray-400">{{ number_format($total) }} {{ Str::plural('movie', $total) }}</p>
            @endif
        </div>

        <div>
            <label for="genre-sort" class="block text-sm font-medium text-gray-200">Sort by</label>
            <select id="genre-sort" wire:model.live="sort"
                    class="mt-1 block rounded-lg border border-white/10 bg-jet px-3 py-2 text-sm text-white focus:ring-[1.5px] focus:ring-neon-rose/75 focus:outline-none">
                @foreach (\App\Livewire\Catalog\GenrePage::SORTS as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div wire:loading.class="opacity-50" class="space-y-6 transition">
        @if ($this->isUnavailable('movies'))
            <x-service-unavailable :service="$unavailable['movies']" :retry="true" />
        @elseif ($movies === [])
            <x-empty-state title="No movies in this genre yet" description="Movies show up here once Catalog has them." />
        @else
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6">
                @foreach ($movies as $movie)
                    <x-movie-card wire:key="genre-movie-{{ $movie['id'] }}" :movie="$movie" :href="route('movies.show', $movie['id'])" />
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
</div>
