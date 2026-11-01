<div class="mx-auto max-w-3xl space-y-6">
    <div class="flex items-center justify-between gap-4">
        <h1 class="text-2xl font-bold tracking-tight text-white">Notifications</h1>

        @if (! $this->isUnavailable('notifications') && $notifications !== [])
            <x-button variant="ghost" size="sm" wire:click="markAllAsRead" wire:loading.attr="disabled">Mark all as read</x-button>
        @endif
    </div>

    @if ($this->isUnavailable('notifications'))
        <x-service-unavailable :service="$unavailable['notifications']" :retry="true" />
    @elseif ($notifications === [])
        <x-empty-state title="No notifications yet" description="Follows, movie night invites and other updates will show up here." />
    @else
        <div class="divide-y divide-white/5 rounded-2xl border border-white/10 bg-white/[0.02]">
            @foreach ($notifications as $notification)
                @include('livewire.notifications.item', ['notification' => $notification, 'compact' => false])
            @endforeach
        </div>

        @if ($lastPage > 1)
            <nav class="flex items-center justify-between text-sm text-gray-400" aria-label="Pagination">
                <x-button variant="ghost" size="sm" wire:click="goToPage({{ $page - 1 }})" :disabled="$page <= 1">Previous</x-button>
                <span>Page {{ $page }} of {{ $lastPage }} · {{ $total }} notifications</span>
                <x-button variant="ghost" size="sm" wire:click="goToPage({{ $page + 1 }})" :disabled="$page >= $lastPage">Next</x-button>
            </nav>
        @endif
    @endif
</div>
