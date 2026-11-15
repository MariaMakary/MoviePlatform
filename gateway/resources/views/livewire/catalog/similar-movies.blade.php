<section id="similar-section" class="space-y-4" aria-labelledby="similar-title">
    <div>
        <h2 id="similar-title" class="text-xl font-bold text-white">Similar movies</h2>
        <p class="text-sm text-gray-400">If you liked this one.</p>
    </div>

    @if ($this->isUnavailable('similar'))
        <x-service-unavailable :service="$unavailable['similar']" :retry="true" />
    @elseif ($movies === [])
        <x-empty-state title="No similar movies found" />
    @else
        <x-movie-row label="Similar movies">
            @foreach ($movies as $similar)
                <x-movie-row.item wire:key="similar-{{ $similar['id'] }}">
                    <x-movie-card :movie="$similar" :href="route('movies.show', $similar['id'])" />
                </x-movie-row.item>
            @endforeach
        </x-movie-row>
    @endif
</section>
