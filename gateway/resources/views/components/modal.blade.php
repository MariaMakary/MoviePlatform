@props(['name', 'title' => null, 'maxWidth' => 'md'])

@php
    $widths = ['sm' => 'sm:max-w-sm', 'md' => 'sm:max-w-md', 'lg' => 'sm:max-w-lg', 'xl' => 'sm:max-w-xl', '2xl' => 'sm:max-w-2xl'];
@endphp

<div x-data="{ show: false, name: @js($name) }"
     x-on:open-modal.window="if (($event.detail?.name ?? $event.detail) === name) show = true"
     x-on:close-modal.window="if (($event.detail?.name ?? $event.detail) === name) show = false"
     x-on:keydown.escape.window="show = false"
     x-show="show" x-cloak
     class="fixed inset-0 z-50 flex items-end justify-center px-4 py-6 sm:items-center"
     role="dialog" aria-modal="true" @if ($title) aria-labelledby="modal-title-{{ $name }}" @endif>
    <div x-show="show" x-transition.opacity class="fixed inset-0 bg-black/70" x-on:click="show = false" aria-hidden="true"></div>

    <div x-show="show" x-trap.noscroll="show"
         x-transition:enter="ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-4 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
         x-transition:leave="ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         {{ $attributes->class(['relative w-full overflow-hidden rounded-2xl border border-white/10 bg-jet shadow-2xl', $widths[$maxWidth]]) }}>
        <div class="flex items-start justify-between gap-4 border-b border-white/10 px-5 py-4">
            @if ($title)
                <h2 id="modal-title-{{ $name }}" class="text-lg font-semibold text-white">{{ $title }}</h2>
            @endif
            <button type="button" x-on:click="show = false" class="ml-auto rounded-md p-1 text-gray-400 hover:bg-white/5 hover:text-white" aria-label="Close">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <div class="px-5 py-4 text-sm text-gray-300">{{ $slot }}</div>

        @isset($footer)
            <div class="flex justify-end gap-2 border-t border-white/10 px-5 py-4">{{ $footer }}</div>
        @endisset
    </div>
</div>
