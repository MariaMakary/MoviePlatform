<section id="weather-row" aria-labelledby="weather-row-title" class="space-y-4"
         x-data="{
             ask() {
                 if (! navigator.geolocation) {
                     return $wire.locate(null, null);
                 }

                 let answered = false;
                 const answer = (lat, lon) => { if (! answered) { answered = true; $wire.locate(lat, lon); } };
                 setTimeout(() => answer(null, null), 8000);
                 navigator.geolocation.getCurrentPosition(
                     (position) => answer(position.coords.latitude, position.coords.longitude),
                     () => answer(null, null),
                     { timeout: 7000, maximumAge: 600000 },
                 );
             },
         }"
         @if ($lat === null) x-init="ask()" @endif>
    <div class="flex flex-wrap items-end justify-between gap-x-6 gap-y-3">
        <div>
            <h2 id="weather-row-title" class="text-xl font-bold text-white sm:text-2xl">Perfect for this weather</h2>
            <p class="text-sm text-gray-400">Movies picked for the weather outside.</p>
        </div>

        @if ($suggestion)
            @php($weather = $suggestion['weather'])
            <div class="flex items-center gap-3 rounded-2xl border border-white/10 bg-white/[0.03] px-4 py-2">
                <x-icons.weather :condition="$weather['condition']" @class(['h-8 w-8 shrink-0',
                    'text-amber-300' => $weather['condition'] === 'clear',
                    'text-sky-300' => in_array($weather['condition'], ['rain', 'snow'], true),
                    'text-neon-rose' => $weather['condition'] === 'storm',
                    'text-gray-300' => $weather['condition'] === 'clouds']) />
                <div class="leading-tight">
                    <p class="text-sm font-semibold text-white">
                        <span>{{ round($weather['temp']) }}°C</span>
                        <span class="font-normal text-gray-300">· {{ $weather['phrase'] }}</span>
                    </p>
                    <p class="text-xs text-gray-400">
                        {{ $weather['city'] ?? 'Your area' }}@if ($usingFallback) · location is off, so this is {{ $fallbackCity }}@endif
                    </p>
                </div>
            </div>
        @endif
    </div>

    @if ($lat === null)
        <div class="flex items-center gap-3 rounded-2xl border border-dashed border-white/15 px-6 py-10 text-sm text-gray-400" role="status">
            <x-icons.weather condition="clouds" class="h-5 w-5 animate-pulse" />
            Checking the weather where you are…
        </div>
    @elseif ($this->isUnavailable('weather'))
        <x-service-unavailable :service="$unavailable['weather']" :retry="true" />
    @elseif ($suggestion['movies'] === [])
        <x-empty-state title="No {{ Str::lower(implode(' or ', $suggestion['genres'])) }} movies yet" description="Movies show up here once Catalog has some in these genres." />
    @else
        <p class="text-sm text-gray-400">{{ implode(' and ', $suggestion['genres']) }} for a {{ Str::lower($suggestion['weather']['phrase']) }} day.</p>
        <x-movie-row label="Perfect for this weather">
            @foreach ($suggestion['movies'] as $movie)
                <x-movie-row.item wire:key="weather-{{ $movie['id'] }}">
                    <x-movie-card :movie="$movie" :href="route('movies.show', $movie['id'])" />
                </x-movie-row.item>
            @endforeach
        </x-movie-row>
    @endif
</section>
