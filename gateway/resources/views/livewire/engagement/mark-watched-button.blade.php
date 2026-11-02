@php
    $classes = $compact
        ? 'inline-flex w-full items-center justify-center gap-2 rounded-lg bg-white/5 px-3 py-1.5 text-sm font-semibold text-gray-100 ring-1 ring-white/10 transition hover:bg-white/10'
        : 'inline-flex items-center gap-2 rounded-lg bg-white/5 px-3 py-2 text-sm font-semibold text-gray-100 ring-1 ring-white/10 transition hover:bg-white/10';
@endphp

<div @class(['inline-flex flex-col items-start gap-1', 'w-full' => $compact])>
    @if ($loggedIn && $this->isUnavailable('history'))
        <x-service-unavailable :service="$unavailable['history']" :retry="true" compact />
    @elseif ($loggedIn && ! $checked)
        {{-- State not loaded yet --}}
    @else
        <button type="button" wire:click="{{ $loggedIn ? 'open' : 'logIn' }}" wire:loading.attr="disabled"
                title="{{ $watchedAt ? 'Mark as watched again' : 'Mark as watched' }}"
                @class([$classes, 'focus-visible:ring-2 focus-visible:ring-cerulean focus-visible:outline-none disabled:opacity-50'])>
            <svg @class(['h-5 w-5 shrink-0', 'text-cerulean' => $watchedAt]) fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
            </svg>
            @if ($watchedAt)
                <span>Watched on <x-date :value="$watchedAt" format="j M Y" /></span>
            @else
                <span>Mark as watched</span>
            @endif
        </button>
    @endif

    @if ($loggedIn)
        <x-modal :name="$modal" title="Mark as watched" max-width="sm">
            <form wire:submit="save" id="{{ $modal }}-form" class="space-y-3">
                <p>When did you watch it? This also removes the movie from your watchlist.</p>
                {{-- Not x-input: its id is the field name, and a watchlist page has one of these modals per movie --}}
                <div>
                    <label for="{{ $modal }}-date" class="block text-sm font-medium text-gray-200">Date</label>
                    <input id="{{ $modal }}-date" type="date" wire:model="date" max="{{ $today }}" required
                           @error('date') aria-invalid="true" aria-describedby="{{ $modal }}-date-error" @enderror
                           @class([
                               'mt-1 block w-full rounded-lg border bg-white/5 px-3 py-2 text-sm text-white [color-scheme:dark] focus:ring-[1.5px] focus:ring-neon-rose/75 focus:outline-none',
                               'border-white/10' => ! $errors->has('date'),
                               'border-neon-rose' => $errors->has('date'),
                           ])>
                    @error('date')
                        <p id="{{ $modal }}-date-error" class="mt-1 text-sm text-neon-rose">{{ $message }}</p>
                    @enderror
                </div>
                @if ($error)
                    <p role="alert" class="text-sm text-neon-rose">{{ $error }}</p>
                @endif
            </form>

            <x-slot:footer>
                <x-button variant="ghost" x-on:click="$dispatch('close-modal', '{{ $modal }}')">Cancel</x-button>
                <x-button type="submit" form="{{ $modal }}-form" wire:loading.attr="disabled" wire:target="save">Save</x-button>
            </x-slot:footer>
        </x-modal>
    @endif
</div>
