<div class="space-y-8">
    @if ($this->isUnavailable('person'))
        <x-service-unavailable :service="$unavailable['person']" :retry="true" />
    @elseif ($person)
        <section class="flex flex-col gap-6 sm:flex-row">
            <div class="shrink-0">
                @if ($person['photo_url'])
                    <img src="{{ $person['photo_url'] }}" alt="{{ $person['name'] }}"
                         class="h-60 w-40 rounded-2xl object-cover ring-1 ring-line">
                @else
                    <x-avatar :name="$person['name']" size="xl" />
                @endif
            </div>

            <div class="min-w-0 space-y-3">
                <h1 class="text-3xl font-bold tracking-tight text-fg">{{ $person['name'] }}</h1>

                <dl class="flex flex-wrap gap-x-8 gap-y-2 text-sm">
                    @if ($person['known_for'])
                        <div>
                            <dt class="text-muted">Known for</dt>
                            <dd class="font-medium text-fg">{{ $person['known_for'] }}</dd>
                        </div>
                    @endif
                    @if ($person['birthday'])
                        <div>
                            <dt class="text-muted">Born</dt>
                            <dd class="font-medium text-fg"><x-date :value="$person['birthday']" format="j F Y" /></dd>
                        </div>
                    @endif
                </dl>

                @if ($person['biography'])
                    <div x-data="{ open: false }" class="max-w-3xl">
                        <p class="leading-relaxed whitespace-pre-line text-muted" @if ($longBiography) :class="open ? '' : 'line-clamp-6'" @endif>{{ $person['biography'] }}</p>
                        @if ($longBiography)
                            <button type="button" x-on:click="open = ! open" :aria-expanded="open.toString()"
                                    class="mt-2 text-sm font-semibold text-cerulean-light hover:underline"
                                    x-text="open ? 'Read less' : 'Read more'">Read more</button>
                        @endif
                    </div>
                @else
                    <p class="text-sm text-subtle">No biography yet.</p>
                @endif
            </div>
        </section>

        @if ($acting === [] && $directing === [])
            <x-empty-state title="No movies yet" description="This person's movies show up here once Catalog has them." />
        @endif

        @foreach (['acting' => ['Acting', $acting], 'directing' => ['Directing', $directing]] as $key => [$title, $movies])
            @if ($movies !== [])
                <section aria-labelledby="{{ $key }}-title" class="space-y-4">
                    <h2 id="{{ $key }}-title" class="text-xl font-bold text-fg">
                        {{ $title }} <span class="text-base font-normal text-muted">({{ count($movies) }})</span>
                    </h2>
                    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6">
                        @foreach ($movies as $movie)
                            <x-movie-card wire:key="{{ $key }}-{{ $movie['id'] }}" :movie="$movie" :href="route('movies.show', $movie['id'])" />
                        @endforeach
                    </div>
                </section>
            @endif
        @endforeach
    @endif
</div>
