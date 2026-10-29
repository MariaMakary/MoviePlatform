@props(['service' => null, 'retry' => false])

<div role="status" {{ $attributes->class('flex flex-col items-center gap-3 rounded-2xl border border-white/10 bg-white/[0.03] px-6 py-8 text-center sm:flex-row sm:text-left') }}>
    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-neon-rose/15 text-neon-rose" aria-hidden="true">
        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/></svg>
    </div>

    <div class="flex-1">
        <p class="text-sm font-semibold text-white">
            {{ $service ? "This part of the page can't be loaded right now." : 'Something is temporarily unavailable.' }}
        </p>
        <p class="text-sm text-gray-400">
            {{ $service ? "The {$service} service isn't responding." : '' }} Please try again in a moment.
        </p>
    </div>

    @if ($retry)
        <x-button variant="ghost" size="sm" wire:click="$refresh" wire:loading.attr="disabled">Try again</x-button>
    @endif
</div>
