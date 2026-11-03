<div class="space-y-6">
    <h1 class="text-2xl font-bold tracking-tight text-white">Search movies</h1>

    {{-- Filters: every change updates the results and the URL --}}
    <div class="grid gap-3 rounded-2xl border border-white/10 bg-white/[0.03] p-4 sm:grid-cols-2 lg:grid-cols-[2fr_1fr_1fr_1fr]">
        <div>
            <label for="search-q" class="block text-sm font-medium text-gray-200">Title</label>
            <input id="search-q" type="search" wire:model.live.debounce.300ms="q" placeholder="Search a movie…" autocomplete="off" maxlength="100" autofocus
                   class="mt-1 block w-full rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-sm text-white placeholder-gray-500 focus:ring-[1.5px] focus:ring-neon-rose/75 focus:outline-none">
        </div>

        @if ($genres !== [])
            <div>
                <label for="search-genre" class="block text-sm font-medium text-gray-200">Genre</label>
                <select id="search-genre" wire:model.live="genre"
                        class="mt-1 block w-full rounded-lg border border-white/10 bg-jet px-3 py-2 text-sm text-white focus:ring-[1.5px] focus:ring-neon-rose/75 focus:outline-none">
                    <option value="">All genres</option>
                    @foreach ($genres as $item)
                        <option value="{{ $item['id'] }}" wire:key="genre-{{ $item['id'] }}">{{ $item['name'] }}</option>
                    @endforeach
                </select>
            </div>
        @endif

        <div>
            <label for="search-year" class="block text-sm font-medium text-gray-200">Year</label>
            <input id="search-year" type="number" inputmode="numeric" min="1870" max="2100" wire:model.live.debounce.500ms="year" placeholder="Any year"
                   class="mt-1 block w-full rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-sm text-white placeholder-gray-500 focus:ring-[1.5px] focus:ring-neon-rose/75 focus:outline-none">
        </div>

        <div>
            <label for="search-sort" class="block text-sm font-medium text-gray-200">Sort by</label>
            <select id="search-sort" wire:model.live="sort"
                    class="mt-1 block w-full rounded-lg border border-white/10 bg-jet px-3 py-2 text-sm text-white focus:ring-[1.5px] focus:ring-neon-rose/75 focus:outline-none">
                @foreach (\App\Livewire\Catalog\Search::SORTS as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>

    @if ($tooShort)
        <p class="text-sm text-gray-400">Type at least 2 letters to search by title.</p>
    @endif

    <div wire:loading.class="opacity-50" class="space-y-6 transition">
        @if ($this->isUnavailable('catalog'))
            <x-service-unavailable :service="$unavailable['catalog']" :retry="true" />
        @elseif ($movies === [])
            <x-empty-state title="No movies found" description="Try another title, or remove a filter." />
        @else
            <p class="text-sm text-gray-400">{{ number_format($total) }} {{ Str::plural('movie', $total) }}</p>

            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6">
                @foreach ($movies as $movie)
                    <x-movie-card wire:key="search-{{ $movie['id'] }}" :movie="$movie" :href="route('movies.show', $movie['id'])" />
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
