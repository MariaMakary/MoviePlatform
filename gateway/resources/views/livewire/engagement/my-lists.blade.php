<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-white">My lists</h1>
            @if (! $this->isUnavailable('lists') && $total > 0)
                <p class="text-sm text-gray-400">{{ $total }} {{ Str::plural('list', $total) }}</p>
            @endif
        </div>
        <div class="flex flex-wrap gap-2">
            <x-button variant="ghost" :href="route('lists.browse')">Browse public lists</x-button>
            <x-button :href="route('lists.create')">
                <x-icons.plus class="h-4 w-4" />
                New list
            </x-button>
        </div>
    </div>

    @if ($this->isUnavailable('lists'))
        <x-service-unavailable :service="$unavailable['lists']" :retry="true" />
    @elseif ($total === 0)
        <x-empty-state title="You have no lists yet" description="Group movies into lists like Favorite comedies or Best of Nolan, and share the public ones.">
            <x-button :href="route('lists.create')">Create a list</x-button>
        </x-empty-state>
    @else
        <ul class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($lists as $list)
                <li wire:key="list-{{ $list['id'] }}">
                    <a href="{{ route('lists.show', $list['id']) }}"
                       class="flex h-full flex-col gap-2 rounded-2xl border border-white/10 bg-white/[0.03] p-5 transition hover:border-cerulean/60 hover:bg-white/5 focus-visible:ring-2 focus-visible:ring-cerulean focus-visible:outline-none">
                        <div class="flex items-start justify-between gap-3">
                            <h2 class="line-clamp-2 font-semibold text-white">{{ $list['title'] }}</h2>
                            @include('livewire.engagement.lists.visibility', ['isPublic' => $list['is_public']])
                        </div>
                        @if ($list['description'])
                            <p class="line-clamp-3 text-sm text-gray-400">{{ $list['description'] }}</p>
                        @endif
                        <p class="mt-auto pt-2 text-xs text-gray-500">Updated <x-date :value="$list['updated_at']" format="j M Y" /></p>
                    </a>
                </li>
            @endforeach
        </ul>

        @if ($lastPage > 1)
            <nav class="flex items-center justify-between text-sm text-gray-400" aria-label="Pagination">
                <x-button variant="ghost" size="sm" wire:click="goToPage({{ $page - 1 }})" :disabled="$page <= 1">Previous</x-button>
                <span>Page {{ $page }} of {{ $lastPage }}</span>
                <x-button variant="ghost" size="sm" wire:click="goToPage({{ $page + 1 }})" :disabled="$page >= $lastPage">Next</x-button>
            </nav>
        @endif
    @endif
</div>
