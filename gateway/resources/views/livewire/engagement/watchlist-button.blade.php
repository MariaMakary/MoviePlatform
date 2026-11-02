@php
    $label = ! $loggedIn ? 'Log in to add' : ($inWatchlist ? 'In watchlist' : 'Add to watchlist');
    $filled = $loggedIn && $inWatchlist;
    $classes = $compact
        ? 'inline-flex h-9 w-9 items-center justify-center rounded-full bg-jet/80 text-gray-100 ring-1 ring-white/15 backdrop-blur transition hover:bg-jet hover:text-white'
        : 'inline-flex items-center gap-2 rounded-lg bg-white/5 px-3 py-2 text-sm font-semibold text-gray-100 ring-1 ring-white/10 transition hover:bg-white/10';
@endphp

<div class="inline-flex flex-col items-start gap-1">
    @if ($loggedIn && $this->isUnavailable('watchlist'))
        <x-service-unavailable :service="$unavailable['watchlist']" :retry="true" compact />
    @elseif ($loggedIn && $inWatchlist === null)
        {{-- State not loaded yet: nothing to toggle --}}
    @else
        <button type="button"
                wire:click="{{ $loggedIn ? 'toggle' : 'logIn' }}"
                wire:loading.attr="disabled"
                @if ($loggedIn) aria-pressed="{{ $inWatchlist ? 'true' : 'false' }}" @endif
                title="{{ $label }}"
                @class([$classes, 'focus-visible:ring-2 focus-visible:ring-cerulean focus-visible:outline-none disabled:opacity-50'])>
            <x-icons.bookmark :filled="$filled" @class(['h-5 w-5', 'text-neon-rose' => $filled]) />
            <span @class(['sr-only' => $compact])>{{ $label }}</span>
        </button>
    @endif

    @if ($error)
        <p role="alert" @class(['text-xs text-neon-rose', 'rounded bg-jet/80 px-1.5 py-0.5' => $compact])>{{ $error }}</p>
    @endif
</div>
