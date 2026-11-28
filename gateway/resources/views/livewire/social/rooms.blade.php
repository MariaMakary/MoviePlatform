<div class="mx-auto max-w-3xl space-y-6">
    <div>
        <h1 class="text-2xl font-bold tracking-tight text-fg">Discussions</h1>
        <p class="text-sm text-muted">One room per movie. Open a movie and press "Discussion" to start one.</p>
    </div>

    @if ($this->isUnavailable('rooms'))
        <x-service-unavailable :service="$unavailable['rooms']" :retry="true" />
    @elseif ($rooms === [])
        <x-empty-state title="No discussions yet" description="Open a movie and press &quot;Discussion&quot; to talk about it." />
    @else
        <ul class="space-y-3">
            @foreach ($rooms as $room)
                <li wire:key="room-{{ $room['id'] }}">
                    <a href="{{ route('movies.discussion', $room['movie_id']) }}"
                       class="flex items-center justify-between gap-4 rounded-2xl border border-line bg-surface-1 px-5 py-4 transition hover:border-cerulean/60 focus-visible:ring-2 focus-visible:ring-cerulean focus-visible:outline-none">
                        <span class="min-w-0 truncate font-semibold text-fg">{{ $room['name'] }}</span>
                        <span class="shrink-0 text-xs text-muted">
                            @if ($room['last_message_at'])
                                Last message <x-date :value="$room['last_message_at']" format="j M, H:i" />
                            @else
                                No messages yet
                            @endif
                        </span>
                    </a>
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
