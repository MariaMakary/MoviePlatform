{{-- One notification, in the bell dropdown ($compact) and on the notifications page. Clicking marks it read and opens its link. --}}
@php($unread = $notification['read_at'] === null)

<button type="button" wire:click="open('{{ $notification['id'] }}')" wire:key="notification-{{ $notification['id'] }}"
        @class(['flex w-full items-start gap-3 text-left transition hover:bg-white/5', 'px-4 py-3' => $compact, 'rounded-xl px-5 py-4' => ! $compact, 'bg-cerulean/10' => $unread])>
    <span @class(['mt-1.5 h-2 w-2 shrink-0 rounded-full', 'bg-neon-rose' => $unread, 'bg-transparent' => ! $unread])
          aria-hidden="true"></span>

    <span class="min-w-0 flex-1">
        <span @class(['block text-sm text-white', 'font-semibold' => $unread])>
            {{ $notification['title'] }}
            @if ($unread)<span class="sr-only">(unread)</span>@endif
        </span>
        <span @class(['block text-sm text-gray-400', 'truncate' => $compact])>{{ $notification['body'] }}</span>
        <x-date :value="$notification['created_at']" class="mt-1 block text-xs text-gray-500" />
    </span>
</button>
