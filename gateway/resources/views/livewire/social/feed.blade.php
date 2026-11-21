<div class="mx-auto max-w-3xl space-y-6">
    <div>
        <h1 class="text-2xl font-bold tracking-tight text-white">Feed</h1>
        <p class="text-sm text-gray-400">What the people you follow rated, reviewed, watched and listed.</p>
    </div>

    @if ($this->isUnavailable('feed'))
        <x-service-unavailable :service="$unavailable['feed']" :retry="true" />
    @elseif ($entries === [])
        <x-empty-state title="Your feed is empty" description="Follow people to see what they rate, review and watch.">
            <x-button :href="route('people')">Find people</x-button>
        </x-empty-state>
    @else
        <ul class="space-y-3">
            @foreach ($entries as $entry)
                <x-activity-card :entry="$entry" :author="$authors[$entry['user_id']] ?? null"
                                 :movie="$entry['movie_id'] ? ($movies[$entry['movie_id']] ?? null) : null"
                                 wire:key="activity-{{ $entry['id'] }}" />
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
