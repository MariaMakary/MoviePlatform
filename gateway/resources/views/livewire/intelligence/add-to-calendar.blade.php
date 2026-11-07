<div class="relative inline-flex flex-col items-start gap-1" x-data="{ open: false }" x-on:keydown.escape="open = false" x-on:click.outside="open = false">
    <button type="button" x-on:click="open = ! open" x-bind:aria-expanded="open" aria-haspopup="menu"
            class="inline-flex items-center gap-2 rounded-lg bg-white/5 px-3 py-2 text-sm font-semibold text-gray-100 ring-1 ring-white/10 transition hover:bg-white/10 focus-visible:ring-2 focus-visible:ring-cerulean focus-visible:outline-none">
        <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/>
        </svg>
        <span>Add to calendar</span>
    </button>

    <div x-show="open" x-cloak x-transition role="menu"
         class="absolute top-full left-0 z-20 mt-2 w-48 overflow-hidden rounded-lg bg-jet py-1 shadow-xl ring-1 ring-white/10">
        <button type="button" role="menuitem" wire:click="download" x-on:click="open = false" wire:loading.attr="disabled"
                class="block w-full px-4 py-2 text-left text-sm text-gray-100 hover:bg-white/10 disabled:opacity-50">
            Download .ics
        </button>
        <button type="button" role="menuitem" wire:click="google" x-on:click="open = false" wire:loading.attr="disabled"
                class="block w-full px-4 py-2 text-left text-sm text-gray-100 hover:bg-white/10 disabled:opacity-50">
            Google Calendar
        </button>
    </div>

    @if ($error)
        <p role="alert" class="text-sm text-neon-rose">{{ $error }}</p>
    @endif
</div>
