<div>
    @if ($loggedIn)
        <section aria-labelledby="recommended-title" class="space-y-4">
            <div>
                <h2 id="recommended-title" class="text-xl font-bold text-fg sm:text-2xl">Recommended for you</h2>
                <p class="text-sm text-muted">Picks based on what you rate and watch.</p>
            </div>

            @if ($this->isUnavailable('recommendations'))
                <x-service-unavailable :service="$unavailable['recommendations']" :retry="true" />
            @elseif ($this->isUnavailable('movies'))
                <x-service-unavailable :service="$unavailable['movies']" :retry="true" />
            @elseif ($items === [])
                <x-empty-state title="No picks yet" description="Rate or watch a few movies and we'll pick some for you." />
            @else
                <x-movie-row label="Recommended for you">
                    @foreach ($items as $item)
                        <x-movie-row.item wire:key="recommended-{{ $item['card']['id'] }}">
                            <x-movie-card :movie="$item['card']" :href="route('movies.show', $item['card']['id'])" />
                            @if ($item['reason'])
                                <p class="mt-1 line-clamp-2 px-0.5 text-xs text-cerulean-light">{{ $item['reason'] }}</p>
                            @endif
                        </x-movie-row.item>
                    @endforeach
                </x-movie-row>
            @endif
        </section>
    @endif
</div>
