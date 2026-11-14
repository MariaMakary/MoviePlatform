<div>
    @if ($mode !== 'hidden')
        @php
            [$title, $subtitle] = $mode === 'recommended'
                ? ['Recommended for you', 'Picks based on what you rate and watch.']
                : ['Popular now', 'Rate and watch movies to get picks made for you.'];
        @endphp

        <section aria-labelledby="recommended-title" class="space-y-4">
            <div>
                <h2 id="recommended-title" class="text-xl font-bold text-white sm:text-2xl">{{ $title }}</h2>
                <p class="text-sm text-gray-400">{{ $subtitle }}</p>
            </div>

            @if ($mode === 'unavailable')
                <x-service-unavailable :service="$unavailable['catalog'] ?? null" :retry="true" />
            @else
                <x-movie-row :label="$title">
                    @foreach ($items as $item)
                        <x-movie-row.item wire:key="recommended-{{ $item['card']['id'] }}">
                            <x-movie-card :movie="$item['card']" :href="route('movies.show', $item['card']['id'])" />
                            @if ($item['reason'])
                                <p class="mt-1 line-clamp-2 px-0.5 text-xs text-sky-300">{{ $item['reason'] }}</p>
                            @endif
                        </x-movie-row.item>
                    @endforeach
                </x-movie-row>
            @endif
        </section>
    @endif
</div>
