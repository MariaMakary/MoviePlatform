<div class="space-y-12">
    @if ($this->isUnavailable('movie'))
        <x-service-unavailable :service="$unavailable['movie']" :retry="true" />
    @else
        @php
            $backdrop = $movie['backdrop_url'];
            $poster = $movie['poster_url'];
            $runtime = $movie['runtime'] ? intdiv($movie['runtime'], 60).'h '.($movie['runtime'] % 60).'m' : null;
        @endphp

        {{-- Header: backdrop, poster and the main facts --}}
        <section class="relative -mx-4 -mt-6 overflow-hidden sm:-mx-6 sm:rounded-b-2xl lg:mx-0 lg:rounded-2xl">
            @if ($backdrop)
                <img src="{{ $backdrop }}" alt="" class="absolute inset-0 h-full w-full object-cover opacity-40">
            @endif
            <div class="absolute inset-0 bg-gradient-to-t from-jet via-jet/80 to-jet/30" aria-hidden="true"></div>

            <div class="relative flex flex-col gap-6 px-4 pt-24 pb-8 sm:flex-row sm:items-end sm:px-8 sm:pt-40">
                <div class="aspect-2/3 w-40 shrink-0 overflow-hidden rounded-xl bg-white/5 shadow-2xl ring-1 ring-white/10 sm:w-52">
                    @if ($poster)
                        <img src="{{ $poster }}" alt="Poster of {{ $movie['title'] }}" class="h-full w-full object-cover">
                    @endif
                </div>

                <div class="min-w-0 flex-1 space-y-4">
                    <div>
                        <h1 class="text-3xl font-bold tracking-tight text-white sm:text-5xl">{{ $movie['title'] }}</h1>
                        <p class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-gray-300">
                            @if ($movie['release_date'])
                                <span>
                                    @if ($upcoming)
                                        <span class="font-medium text-sky-300">Out <x-date :value="$movie['release_date']" format="j M Y" /></span>
                                    @else
                                        {{ $movie['year'] }}
                                    @endif
                                </span>
                            @endif
                            @if ($runtime)
                                <span aria-hidden="true">·</span><span>{{ $runtime }}</span>
                            @endif
                            @if ($movie['vote_average'] !== null)
                                <span aria-hidden="true">·</span>
                                <span class="inline-flex items-center gap-1 font-semibold text-white" aria-label="TMDB rating {{ number_format($movie['vote_average'], 1) }} out of 10">
                                    <x-icons.star class="h-4 w-4 text-neon-rose" />
                                    <span aria-hidden="true">{{ number_format($movie['vote_average'], 1) }}<span class="font-normal text-gray-400">/10</span></span>
                                </span>
                            @endif
                        </p>
                    </div>

                    @if ($movie['genres'] !== [])
                        <ul class="flex flex-wrap gap-2" aria-label="Genres">
                            @foreach ($movie['genres'] as $genre)
                                <li>
                                    <a href="{{ route('search', ['genre' => $genre['id']]) }}"
                                       class="inline-flex rounded-full border border-white/15 bg-white/5 px-3 py-1 text-xs font-medium text-gray-200 transition hover:border-cerulean/60 hover:text-white">{{ $genre['name'] }}</a>
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    {{-- Actions from other tasks: replace each x-reserved with its Livewire component --}}
                    <div class="flex flex-wrap items-center gap-2">
                        <livewire:engagement.rate-movie :movie-id="$movie['id']" :key="'rating-'.$movie['id']" />
                        <livewire:engagement.watchlist-button :movie-id="$movie['id']" :key="'watchlist-'.$movie['id']" />
                        <livewire:engagement.mark-watched-button :movie-id="$movie['id']" :key="'watched-'.$movie['id']" />
                        <livewire:engagement.add-to-list-button :movie-id="$movie['id']" :key="'lists-'.$movie['id']" />
                        @if ($upcoming)
                            <livewire:intelligence.add-to-calendar :movie-id="$movie['id']" :title="$movie['title']"
                                :release-date="$movie['release_date']" :overview="$movie['overview']" :key="'calendar-'.$movie['id']" />
                        @endif
                        <x-reserved compact id="discussion-slot" title="Discussion" />           {{-- Sprint 4 discussion button --}}
                    </div>
                </div>
            </div>
        </section>

        {{-- Overview and director --}}
        <section class="grid gap-8 lg:grid-cols-3">
            <div class="space-y-3 lg:col-span-2">
                <h2 class="text-xl font-bold text-white">Overview</h2>
                <p class="leading-relaxed text-gray-300">{{ $movie['overview'] ?? 'No overview yet.' }}</p>
            </div>

            @if ($movie['directors'] !== [])
                <div class="space-y-3">
                    <h2 class="text-xl font-bold text-white">{{ count($movie['directors']) > 1 ? 'Directors' : 'Director' }}</h2>
                    <ul class="space-y-3">
                        @foreach ($movie['directors'] as $director)
                            <li>
                                <a href="{{ route('people.show', $director['id']) }}" class="group flex items-center gap-3">
                                    <x-avatar :name="$director['name']" :src="$director['photo_url']" size="md" />
                                    <span class="font-medium text-gray-100 group-hover:text-white group-hover:underline">{{ $director['name'] }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </section>

        {{-- Cast --}}
        @if ($movie['cast'] !== [])
            <section aria-labelledby="cast-title" class="space-y-4">
                <h2 id="cast-title" class="text-xl font-bold text-white">Cast</h2>
                <x-movie-row label="Cast">
                    @foreach ($movie['cast'] as $person)
                        <x-movie-row.item wire:key="cast-{{ $person['id'] }}" width="w-28" class="text-center">
                            <a href="{{ route('people.show', $person['id']) }}" class="group block rounded-xl focus-visible:ring-2 focus-visible:ring-cerulean focus-visible:outline-none">
                                <x-avatar :name="$person['name']" :src="$person['photo_url']" size="xl" class="mx-auto transition group-hover:opacity-85" />
                                <p class="mt-2 line-clamp-2 text-sm font-semibold text-gray-100 group-hover:text-white group-hover:underline">{{ $person['name'] }}</p>
                                @if ($person['character'])
                                    <p class="line-clamp-2 text-xs text-gray-400">{{ $person['character'] }}</p>
                                @endif
                            </a>
                        </x-movie-row.item>
                    @endforeach
                </x-movie-row>
            </section>
        @endif

        {{-- Trailer --}}
        @if ($movie['trailer_key'])
            <section aria-labelledby="trailer-title" class="space-y-4">
                <h2 id="trailer-title" class="text-xl font-bold text-white">Trailer</h2>
                <div class="aspect-video w-full max-w-4xl overflow-hidden rounded-2xl bg-black ring-1 ring-white/10" wire:ignore>
                    <iframe src="https://www.youtube-nocookie.com/embed/{{ rawurlencode($movie['trailer_key']) }}"
                            title="Trailer of {{ $movie['title'] }}" class="h-full w-full" loading="lazy"
                            allow="accelerometer; encrypted-media; gyroscope; picture-in-picture; fullscreen"
                            referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>
                </div>
            </section>
        @endif

        {{-- Sections from later sprints: replace each x-reserved with its Livewire component --}}
        <livewire:engagement.movie-reviews :movie-id="$movie['id']" :key="'reviews-'.$movie['id']" />
        <x-reserved id="lists-section" title="In lists" subtitle="Lists that include this movie." />         {{-- Sprint 3 --}}
        <x-reserved id="similar-section" title="Similar movies" subtitle="If you liked this one." />        {{-- 1311, Sprint 4 --}}
    @endif
</div>
