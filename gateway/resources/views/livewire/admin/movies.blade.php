<x-admin.layout title="Movies">
    <section class="space-y-3 rounded-2xl border border-white/10 bg-white/[0.03] p-5" aria-labelledby="import-title">
        <h2 id="import-title" class="text-sm font-semibold text-white">Import from TMDB</h2>
        <form wire:submit="import" class="flex flex-wrap items-start gap-2">
            <div class="min-w-48 flex-1">
                <label for="tmdbId" class="sr-only">TMDB id</label>
                <input id="tmdbId" type="text" inputmode="numeric" wire:model="tmdbId" placeholder="TMDB id, e.g. 27205" autocomplete="off"
                       @error('tmdbId') aria-invalid="true" aria-describedby="tmdbId-error" @enderror
                       @class(['block w-full rounded-lg border bg-white/5 px-3 py-2 text-sm text-white placeholder-gray-500 focus:ring-[1.5px] focus:ring-neon-rose/75 focus:outline-none',
                               'border-white/10' => ! $errors->has('tmdbId'), 'border-neon-rose' => $errors->has('tmdbId')])>
                @error('tmdbId')
                    <p id="tmdbId-error" class="mt-1 text-sm text-neon-rose">{{ $message }}</p>
                @enderror
            </div>
            <x-button type="submit" variant="secondary" wire:loading.attr="disabled" wire:target="import">Import</x-button>
        </form>
        <p class="text-xs text-gray-500">The id is the number in the movie's TMDB address: themoviedb.org/movie/<strong>27205</strong>-inception.</p>
        @if ($this->isUnavailable('import'))
            <x-service-unavailable :service="$unavailable['import']" compact />
        @endif
    </section>

    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="w-full sm:w-72 sm:max-w-full">
            <label for="q" class="sr-only">Search by title</label>
            <div class="relative">
                <input id="q" type="search" wire:model.live.debounce.400ms="q" placeholder="Search by title…" maxlength="100"
                       class="block w-full rounded-lg border border-white/10 bg-white/5 py-2 pl-3 pr-10 text-sm text-white placeholder-gray-500 focus:ring-[1.5px] focus:ring-neon-rose/75 focus:outline-none">
                <x-search-spinner target="q" />
            </div>
        </div>
        @unless ($this->isUnavailable('movies'))
            <p class="text-sm text-gray-400">{{ $meta['total'] }} {{ $meta['total'] === 1 ? 'movie' : 'movies' }}</p>
        @endunless
    </div>

    @if ($this->isUnavailable('movies'))
        <x-service-unavailable :service="$unavailable['movies']" :retry="true" />
    @elseif ($movies === [])
        <x-empty-state title="No movies found" :description="$q !== '' ? 'No title matches “'.$q.'”.' : 'Import one from TMDB above, or run catalog:sync-popular.'" />
    @else
        <div class="overflow-x-auto rounded-2xl border border-white/10">
            <table class="w-full min-w-[36rem] text-left text-sm">
                <thead class="bg-white/[0.03] text-xs tracking-wide text-gray-400 uppercase">
                    <tr>
                        <th scope="col" class="px-4 py-3"><span class="sr-only">Poster</span></th>
                        <th scope="col" class="px-4 py-3">Title</th>
                        <th scope="col" class="px-4 py-3">Year</th>
                        <th scope="col" class="px-4 py-3"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/10">
                    @foreach ($movies as $movie)
                        <tr wire:key="movie-{{ $movie['id'] }}" class="hover:bg-white/[0.02]">
                            <td class="w-14 px-4 py-2">
                                @if ($movie['poster_url'])
                                    <img src="{{ \App\Services\TmdbImage::url($movie['poster_path'], 'w92') }}" alt="" class="h-15 w-10 rounded object-cover" loading="lazy">
                                @else
                                    <div class="h-15 w-10 rounded bg-white/5" aria-hidden="true"></div>
                                @endif
                            </td>
                            <td class="px-4 py-2">
                                <a href="{{ route('movies.show', $movie['id']) }}" class="font-medium text-white hover:underline">{{ $movie['title'] }}</a>
                                @if ($movie['is_edited'])
                                    <span class="ml-2 inline-flex rounded-full bg-cerulean/15 px-2 py-0.5 text-xs font-semibold text-cerulean">Edited</span>
                                @endif
                                @if ($movie['tmdb_id'] === null)
                                    <span class="ml-1 inline-flex rounded-full bg-white/10 px-2 py-0.5 text-xs text-gray-300">Not from TMDB</span>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-gray-300">{{ $movie['release_date'] ? substr($movie['release_date'], 0, 4) : '–' }}</td>
                            <td class="px-4 py-2">
                                <div class="flex justify-end gap-2">
                                    <x-button size="sm" variant="ghost" wire:click="edit('{{ $movie['id'] }}')" wire:loading.attr="disabled" wire:target="edit('{{ $movie['id'] }}')">Edit</x-button>
                                    <x-button size="sm" variant="danger" wire:click="confirmDelete('{{ $movie['id'] }}', @js($movie['title']))">Delete</x-button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($meta['last_page'] > 1)
            <nav class="flex items-center justify-between text-sm text-gray-400" aria-label="Pagination">
                <x-button variant="ghost" size="sm" wire:click="goToPage({{ $page - 1 }})" :disabled="$page <= 1">Previous</x-button>
                <span>Page {{ $page }} of {{ $meta['last_page'] }}</span>
                <x-button variant="ghost" size="sm" wire:click="goToPage({{ $page + 1 }})" :disabled="$page >= $meta['last_page']">Next</x-button>
            </nav>
        @endif
    @endif

    <x-modal name="edit-movie" title="Edit movie" max-width="2xl">
        @if ($editingId)
            <form wire:submit="save" id="edit-movie-form" class="space-y-4">
                <x-input label="Title" name="title" wire:model="title" maxlength="255" required />

                <div>
                    <label for="overview" class="block text-sm font-medium text-gray-200">Overview</label>
                    <textarea id="overview" wire:model="overview" rows="4" maxlength="5000"
                              @class(['mt-1 block w-full rounded-lg border bg-white/5 px-3 py-2 text-sm text-white placeholder-gray-500 focus:ring-[1.5px] focus:ring-neon-rose/75 focus:outline-none',
                                      'border-white/10' => ! $errors->has('overview'), 'border-neon-rose' => $errors->has('overview')])></textarea>
                    @error('overview')
                        <p class="mt-1 text-sm text-neon-rose">{{ $message }}</p>
                    @enderror
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-input label="Release date" name="release_date" type="date" wire:model="release_date" />
                    <x-input label="Runtime (minutes)" name="runtime" type="number" min="1" max="1000" wire:model="runtime" />
                </div>

                <div x-data="{ path: $wire.entangle('poster_path'), base: @js($imageBase) }" class="flex items-start gap-4">
                    <div class="flex-1">
                        <x-input label="Poster path" name="poster_path" x-model="path" placeholder="/oYuLEt3zVCKq57qu2F8dT7NIa6f.jpg" maxlength="255" />
                        <p class="mt-1 text-xs text-gray-500">The part after the size in a TMDB image address.</p>
                    </div>
                    <div class="h-24 w-16 shrink-0 overflow-hidden rounded bg-white/5" aria-hidden="true">
                        <template x-if="/^\/[A-Za-z0-9_.-]+$/.test(path ?? '')">
                            <img :src="base + path" alt="" class="h-full w-full object-cover">
                        </template>
                    </div>
                </div>

                <fieldset>
                    <legend class="block text-sm font-medium text-gray-200">Genres</legend>
                    @if ($this->isUnavailable('genres'))
                        <x-service-unavailable :service="$unavailable['genres']" compact class="mt-1" />
                    @else
                        <div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-3">
                            @foreach ($genres as $genre)
                                <label wire:key="genre-{{ $genre['id'] }}" class="flex items-center gap-2 text-sm text-gray-200">
                                    <input type="checkbox" value="{{ $genre['id'] }}" wire:model="genre_ids"
                                           class="h-4 w-4 rounded border-white/20 bg-white/5 text-cerulean focus:ring-cerulean">
                                    {{ $genre['name'] }}
                                </label>
                            @endforeach
                        </div>
                    @endif
                    @error('genre_ids')
                        <p class="mt-1 text-sm text-neon-rose">{{ $message }}</p>
                    @enderror
                    @error('genre_ids.*')
                        <p class="mt-1 text-sm text-neon-rose">{{ $message }}</p>
                    @enderror
                </fieldset>

                <p class="text-xs text-gray-500">Saving marks the movie as edited: the TMDB sync will no longer change these fields.</p>

                @if ($this->isUnavailable('save'))
                    <x-service-unavailable :service="$unavailable['save']" compact />
                @endif
            </form>
        @endif

        <x-slot:footer>
            <x-button variant="ghost" x-on:click="$dispatch('close-modal', 'edit-movie')">Cancel</x-button>
            <x-button type="submit" form="edit-movie-form" wire:loading.attr="disabled" wire:target="save">Save</x-button>
        </x-slot:footer>
    </x-modal>

    <x-modal name="delete-movie" title="Delete movie">
        <p>
            Delete <strong class="text-white">{{ $deleting['title'] ?? '' }}</strong>? Its ratings, reviews, list entries and
            chat room are removed in the other services too. This cannot be undone.
        </p>
        @if ($this->isUnavailable('delete'))
            <x-service-unavailable :service="$unavailable['delete']" compact class="mt-3" />
        @endif

        <x-slot:footer>
            <x-button variant="ghost" x-on:click="$dispatch('close-modal', 'delete-movie')">Keep it</x-button>
            <x-button variant="danger" wire:click="delete" wire:loading.attr="disabled" wire:target="delete">Delete</x-button>
        </x-slot:footer>
    </x-modal>
</x-admin.layout>
