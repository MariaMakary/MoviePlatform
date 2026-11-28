@php($host = $hosts->get($night['host_id']))

<a href="{{ route('movie-nights.show', $night['id']) }}" wire:key="night-{{ $night['id'] }}"
   @class(['block rounded-2xl border border-line bg-surface-1 p-4 transition hover:border-line-strong hover:bg-surface-2 focus-visible:ring-2 focus-visible:ring-cerulean focus-visible:outline-none',
           'opacity-60' => $muted])>
    <div class="flex flex-wrap items-start justify-between gap-2">
        <div class="min-w-0 space-y-1">
            <p class="truncate font-semibold text-fg">{{ $night['title'] }}</p>
            <p class="text-sm text-muted">
                <x-date :value="$night['scheduled_at']" format="D j M Y, H:i" />
                @if ($night['location'])
                    · {{ $night['location'] }}
                @elseif ($night['meeting_link'])
                    · Online
                @endif
            </p>
            @if ($night['host_id'] !== $userId && $host)
                <p class="flex items-center gap-2 text-xs text-muted">
                    <x-avatar :name="$host['name']" :src="$host['avatar']" size="xs" />
                    Hosted by {{ $host['name'] }}
                </p>
            @endif
        </div>

        @include('livewire.social.partials.status-badges', ['night' => $night, 'userId' => $userId])
    </div>
</a>
