<div wire:ignore data-cinema-map data-fallback-lat="{{ $fallback['lat'] }}" data-fallback-lng="{{ $fallback['lng'] }}"
     data-radius="{{ $radius }}" class="space-y-4">
    <header>
        <h1 class="text-2xl font-bold text-white">Cinemas near me</h1>
        <p data-map-status role="status" aria-live="polite" class="mt-1 text-sm text-gray-400">Finding your location…</p>
    </header>

    <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
        <form data-place-search role="search" class="flex flex-1 items-end gap-2">
            <div class="flex-1">
                <x-input label="Choose a location" name="place" type="search" placeholder="City, area or address"
                         autocomplete="off" enterkeyhint="search" />
            </div>
            <x-button type="submit" variant="ghost">Search</x-button>
        </form>

        <x-button variant="secondary" data-map-locate>
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z"/>
            </svg>
            Use my location
        </x-button>
    </div>

    <div class="grid gap-4 lg:grid-cols-3">
        {{-- isolate: keeps Leaflet's high z-indexes under the sticky navbar --}}
        <div data-map class="isolate h-[70vh] min-h-80 overflow-hidden rounded-2xl border border-white/10 bg-white/[0.03] lg:col-span-2"
             role="region" aria-label="Map of cinemas near you"></div>

        <aside class="flex max-h-[70vh] min-h-48 flex-col overflow-hidden rounded-2xl border border-white/10 bg-white/[0.03]"
               aria-labelledby="cinemas-heading">
            <div class="border-b border-white/10 px-4 py-3">
                <h2 id="cinemas-heading" class="text-sm font-semibold text-white">Cinemas within {{ $radius / 1000 }} km</h2>
                <p data-cinemas-status role="status" aria-live="polite" class="mt-0.5 text-sm text-gray-400">Waiting for your location…</p>
                <x-button variant="ghost" size="sm" class="mt-3" data-cinemas-retry hidden>Try again</x-button>
            </div>

            <ol data-cinemas-list class="flex-1 divide-y divide-white/10 overflow-y-auto"></ol>
        </aside>
    </div>
</div>
