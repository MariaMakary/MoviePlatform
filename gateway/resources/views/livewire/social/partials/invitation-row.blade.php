@use('MoviePlatform\Common\MovieNights\ParticipantStatus')

<div wire:key="invitation-{{ $night['id'] }}" class="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-cerulean/40 bg-cerulean/[0.06] p-4">
    <div class="flex min-w-0 items-center gap-3">
        @if ($host)
            <x-avatar :name="$host['name']" :src="$host['avatar']" size="md" />
        @endif
        <div class="min-w-0 space-y-0.5">
            <a href="{{ route('movie-nights.show', $night['id']) }}" class="block truncate font-semibold text-white hover:underline">{{ $night['title'] }}</a>
            <p class="text-sm text-gray-300">
                {{ $host ? $host['name'].' invited you' : "You're invited" }} · <x-date :value="$night['scheduled_at']" format="D j M Y, H:i" />
            </p>
        </div>
    </div>

    <div class="flex gap-2">
        <x-button size="sm" wire:click="answer('{{ $night['id'] }}', '{{ ParticipantStatus::Accepted->value }}')"
                  wire:loading.attr="disabled" wire:target="answer">Accept</x-button>
        <x-button size="sm" variant="ghost" wire:click="answer('{{ $night['id'] }}', '{{ ParticipantStatus::Declined->value }}')"
                  wire:loading.attr="disabled" wire:target="answer">Decline</x-button>
    </div>
</div>
