<div wire:ignore data-cinema-map data-fallback-lat="{{ $fallback['lat'] }}" data-fallback-lng="{{ $fallback['lng'] }}" class="space-y-4">
    <header class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-white">Cinemas near me</h1>
            <p data-map-status role="status" aria-live="polite" class="mt-1 text-sm text-gray-400">Finding your location…</p>
        </div>

        <x-button variant="secondary" data-map-locate>
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z"/>
            </svg>
            Use my location
        </x-button>
    </header>

    {{-- isolate: keeps Leaflet's high z-indexes under the sticky navbar --}}
    <div data-map class="isolate h-[70vh] min-h-80 overflow-hidden rounded-2xl border border-white/10 bg-white/[0.03]"
         role="region" aria-label="Map of cinemas near you"></div>
</div>
