<div wire:poll.30s class="relative" x-on:click.outside="$wire.expanded && $wire.close()" x-on:keydown.escape.window="$wire.expanded && $wire.close()">
    <button type="button" wire:click="toggle" class="relative rounded-full p-2 text-gray-300 hover:bg-white/5 hover:text-white"
            aria-label="Notifications{{ $count ? " ({$count} unread)" : '' }}" aria-haspopup="true" aria-expanded="{{ $expanded ? 'true' : 'false' }}">
        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0"/>
        </svg>

        @if ($count)
            <span class="absolute -top-0.5 -right-0.5 flex h-5 min-w-5 items-center justify-center rounded-full bg-neon-rose px-1 text-[10px] font-bold text-jet" aria-hidden="true">
                {{ $count > 99 ? '99+' : $count }}
            </span>
        @endif
    </button>

    @if ($expanded)
        <div class="absolute right-0 z-50 mt-2 w-80 overflow-hidden rounded-xl border border-white/10 bg-jet shadow-xl shadow-black/40 sm:w-96">
            <div class="flex items-center justify-between border-b border-white/10 px-4 py-3">
                <p class="text-sm font-semibold text-white">Notifications</p>
                @if ($count)
                    <button type="button" wire:click="markAllAsRead" class="text-xs font-medium text-cerulean hover:text-white">Mark all as read</button>
                @endif
            </div>

            @if ($this->isUnavailable('notifications'))
                <p class="px-4 py-6 text-center text-sm text-gray-400">Notifications can't be loaded right now.</p>
            @elseif ($latest === [])
                <p class="px-4 py-6 text-center text-sm text-gray-400">No notifications yet.</p>
            @else
                <div class="divide-y divide-white/5">
                    @foreach ($latest as $notification)
                        @include('livewire.notifications.item', ['notification' => $notification, 'compact' => true])
                    @endforeach
                </div>
            @endif

            <a href="{{ route('notifications') }}" class="block border-t border-white/10 px-4 py-3 text-center text-sm font-medium text-cerulean hover:bg-white/5 hover:text-white">
                See all notifications
            </a>
        </div>
    @endif
</div>
