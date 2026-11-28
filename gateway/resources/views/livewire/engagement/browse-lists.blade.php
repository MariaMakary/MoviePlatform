<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-fg">Browse lists</h1>
            <p class="text-sm text-muted">Movie lists shared by the community.</p>
        </div>
        @if ($loggedIn)
            <x-button variant="ghost" :href="route('lists.index')">My lists</x-button>
        @endif
    </div>

    <div class="max-w-md">
        <label for="lists-search" class="sr-only">Search lists by title</label>
        <div class="relative">
            <input id="lists-search" type="search" wire:model.live.debounce.300ms="q" placeholder="Search lists by title…" autocomplete="off" maxlength="150"
                   class="block w-full rounded-lg border border-line-strong bg-surface-1 py-2 pl-3 pr-10 text-sm text-fg placeholder-subtle focus:ring-[1.5px] focus:ring-neon-rose/75 focus:outline-none">
            <x-search-spinner target="q" />
        </div>
    </div>

    <div wire:loading.class="opacity-50" class="space-y-6 transition">
        @if ($this->isUnavailable('lists'))
            <x-service-unavailable :service="$unavailable['lists']" :retry="true" />
        @elseif ($lists === [])
            <x-empty-state :title="trim($q) === '' ? 'No public lists yet' : 'No lists found'"
                           :description="trim($q) === '' ? 'Public lists will show up here.' : 'Try another title.'" />
        @else
            <p class="text-sm text-muted">{{ number_format($total) }} {{ Str::plural('list', $total) }}</p>

            <ul class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($lists as $list)
                    @php
                        $owner = $owners[$list['user_id']] ?? null;
                        $ownerName = $owner['name'] ?? 'KAIA member';
                        $collage = array_pad($list['first_movie_ids'], 4, null);
                    @endphp
                    <li wire:key="list-{{ $list['id'] }}" class="flex flex-col overflow-hidden rounded-2xl border border-line bg-surface-1 transition hover:border-cerulean/60">
                        <a href="{{ route('lists.show', $list['id']) }}" class="group flex flex-1 flex-col focus-visible:ring-2 focus-visible:ring-cerulean focus-visible:outline-none">
                            {{-- The first 4 posters; an empty tile when the list is shorter or Catalog is down --}}
                            <div class="grid grid-cols-4 gap-px bg-surface-1" aria-hidden="true">
                                @foreach ($collage as $movieId)
                                    <div class="aspect-2/3 bg-jet">
                                        @if ($movieId && ($posters[$movieId] ?? null))
                                            <img src="{{ $posters[$movieId] }}" alt="" loading="lazy" class="h-full w-full object-cover transition group-hover:opacity-90">
                                        @endif
                                    </div>
                                @endforeach
                            </div>

                            <div class="flex flex-1 flex-col gap-1 p-4">
                                <h2 class="line-clamp-2 font-semibold text-fg group-hover:text-cerulean-light">{{ $list['title'] }}</h2>
                                @if ($list['description'])
                                    <p class="line-clamp-2 text-sm wrap-anywhere text-muted">{{ $list['description'] }}</p>
                                @endif
                                <p class="mt-auto pt-2 text-xs text-subtle">{{ $list['movie_count'] }} {{ Str::plural('movie', $list['movie_count']) }}</p>
                            </div>
                        </a>

                        {{-- Outside the card link: a link cannot hold another link. --}}
                        <div class="flex items-center gap-2 border-t border-line px-4 py-3">
                            <x-avatar :name="$ownerName" :src="$owner['avatar'] ?? null" size="xs" />
                            @if ($owner)
                                <a href="{{ route('users.show', $list['user_id']) }}" class="truncate text-sm text-muted hover:text-fg hover:underline">{{ $ownerName }}</a>
                            @else
                                <span class="truncate text-sm text-muted">{{ $ownerName }}</span>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>

            @if ($lastPage > 1)
                <nav class="flex items-center justify-between text-sm text-muted" aria-label="Pagination">
                    <x-button variant="ghost" size="sm" wire:click="goToPage({{ $page - 1 }})" :disabled="$page <= 1">Previous</x-button>
                    <span>Page {{ $page }} of {{ $lastPage }}</span>
                    <x-button variant="ghost" size="sm" wire:click="goToPage({{ $page + 1 }})" :disabled="$page >= $lastPage">Next</x-button>
                </nav>
            @endif
        @endif
    </div>
</div>
