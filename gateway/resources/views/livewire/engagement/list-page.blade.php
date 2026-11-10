<div class="space-y-6">
    @if ($this->isUnavailable('list'))
        <x-service-unavailable :service="$unavailable['list']" :retry="true" />
    @else
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0 space-y-2">
                @if ($isOwner)
                    <a href="{{ route('lists.index') }}" class="text-sm text-gray-400 hover:text-white">&larr; My lists</a>
                @endif
                <div class="flex flex-wrap items-center gap-3">
                    <h1 class="text-2xl font-bold tracking-tight text-white sm:text-3xl">{{ $list['title'] }}</h1>
                    @include('livewire.engagement.lists.visibility', ['isPublic' => $list['is_public']])
                </div>
                @if ($list['description'])
                    <p class="max-w-2xl text-gray-300">{{ $list['description'] }}</p>
                @endif
                <p class="text-sm text-gray-400">
                    {{ count($list['movie_ids']) }} {{ Str::plural('movie', count($list['movie_ids'])) }} · Updated <x-date :value="$list['updated_at']" format="j M Y" />
                </p>
            </div>

            @if ($isOwner)
                <div class="flex gap-2">
                    <x-button variant="ghost" size="sm" :href="route('lists.edit', $list['id'])">Edit</x-button>
                    <x-button variant="danger" size="sm" x-data x-on:click="$dispatch('open-modal', 'delete-list')">Delete</x-button>
                </div>
            @endif
        </div>

        @if ($error)
            <p role="alert" class="text-sm text-neon-rose">{{ $error }}</p>
        @endif

        @if ($list['movie_ids'] === [])
            <x-empty-state title="This list is empty" :description="$isOwner ? 'Open a movie and use Add to list to put it here.' : 'No movies here yet.'">
                @if ($isOwner)
                    <x-button :href="route('home')">Browse movies</x-button>
                @endif
            </x-empty-state>
        @elseif ($this->isUnavailable('catalog'))
            <x-service-unavailable :service="$unavailable['catalog']" :retry="true" />
        @else
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6">
                @foreach ($movies as $movie)
                    <div wire:key="list-movie-{{ $movie['id'] }}" class="space-y-2">
                        <x-movie-card :movie="$movie" :href="route('movies.show', $movie['id'])" />
                        @if ($isOwner)
                            <x-button variant="ghost" size="sm" class="w-full"
                                      wire:click="remove('{{ $movie['id'] }}')" wire:loading.attr="disabled" wire:target="remove('{{ $movie['id'] }}')">
                                Remove
                            </x-button>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif

        @if ($isOwner)
            <x-modal name="delete-list" title="Delete this list?">
                This deletes "{{ $list['title'] }}" and removes its movies from it. The movies themselves stay in your watchlist and history.

                <x-slot:footer>
                    <x-button variant="ghost" x-on:click="$dispatch('close-modal', 'delete-list')">Cancel</x-button>
                    <x-button variant="danger" wire:click="deleteList" wire:loading.attr="disabled" wire:target="deleteList">Delete</x-button>
                </x-slot:footer>
            </x-modal>
        @endif
    @endif
</div>
