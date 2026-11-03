@php($toasts = [
    'success' => ['accent' => 'bg-cerulean', 'icon' => 'text-cerulean', 'seconds' => 3, 'path' => 'm4.5 12.75 6 6 9-13.5'],
    'info' => ['accent' => 'bg-cerulean', 'icon' => 'text-cerulean', 'seconds' => 3, 'path' => 'M11.25 11.25l.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M12 8.25h.008v.008H12V8.25Z'],
    'error' => ['accent' => 'bg-neon-rose', 'icon' => 'text-neon-rose', 'seconds' => 5, 'path' => 'M12 9v3.75m0 3.75h.008v.008H12v-.008Z'],
])

<div class="pointer-events-none fixed inset-x-4 top-20 z-50 flex flex-col items-center gap-2 sm:inset-x-auto sm:right-6 sm:items-end">
    @foreach ($toasts as $type => $toast)
        @if (session()->has($type))
            <div x-data="{ show: false, timer: null, start() { this.timer = setTimeout(() => this.show = false, {{ $toast['seconds'] * 1000 }}) }, stop() { clearTimeout(this.timer) } }"
                 x-init="$nextTick(() => { show = true; start() })"
                 x-show="show" style="display: none"
                 x-transition:enter="transition duration-300 ease-out" x-transition:enter-start="translate-y-2 opacity-0" x-transition:enter-end="translate-y-0 opacity-100"
                 x-transition:leave="transition duration-200 ease-in" x-transition:leave-start="opacity-100" x-transition:leave-end="translate-y-2 opacity-0"
                 @mouseenter="stop()" @mouseleave="start()"
                 role="{{ $type === 'error' ? 'alert' : 'status' }}"
                 class="pointer-events-auto relative flex w-full max-w-sm items-center gap-3 overflow-hidden rounded-xl border border-white/10 bg-jet/95 py-3 pr-3 pl-5 text-sm text-white shadow-2xl shadow-cerulean/20 backdrop-blur">
                <span class="absolute inset-y-0 left-0 w-1 {{ $toast['accent'] }}" aria-hidden="true"></span>
                <svg class="h-5 w-5 shrink-0 {{ $toast['icon'] }}" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $toast['path'] }}"/></svg>
                <p class="flex-1">{{ session($type) }}</p>
                <button type="button" @click="show = false" class="shrink-0 rounded p-1 text-gray-400 transition hover:bg-white/10 hover:text-white" aria-label="Dismiss">&times;</button>
            </div>
        @endif
    @endforeach
</div>
