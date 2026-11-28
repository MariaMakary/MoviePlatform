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
                <div class="aspect-2/3 w-40 shrink-0 overflow-hidden rounded-xl bg-surface-1 shadow-2xl ring-1 ring-line sm:w-52">
                    @if ($poster)
                        <img src="{{ $poster }}" alt="Poster of {{ $movie['title'] }}" class="h-full w-full object-cover">
                    @endif
                </div>

                <div class="min-w-0 flex-1 space-y-4">
                    <div>
                        <h1 class="text-3xl font-bold tracking-tight wrap-anywhere text-fg sm:text-5xl">{{ $movie['title'] }}</h1>
                        <p class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-muted">
                            @if ($movie['release_date'])
                                <span>
                                    @if ($upcoming)
                                        <span class="font-medium text-cerulean-light">Out <x-date :value="$movie['release_date']" format="j M Y" /></span>
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
                                <span class="inline-flex items-center gap-1 font-semibold text-fg" aria-label="TMDB rating {{ number_format($movie['vote_average'], 1) }} out of 10">
                                    <x-icons.star class="h-4 w-4 text-neon-rose" />
                                    <span aria-hidden="true">{{ number_format($movie['vote_average'], 1) }}<span class="font-normal text-muted">/10</span></span>
                                </span>
                            @endif
                        </p>
                    </div>

                    @if ($movie['genres'] !== [])
                        <ul class="flex flex-wrap gap-2" aria-label="Genres">
                            @foreach ($movie['genres'] as $genre)
                                <li>
                                    <a href="{{ route('search', ['genre' => $genre['id']]) }}"
                                       class="inline-flex rounded-full border border-line-strong bg-surface-1 px-3 py-1 text-xs font-medium text-fg transition hover:border-cerulean/60 hover:text-fg">{{ $genre['name'] }}</a>
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
                            <livewire:intelligence.add-to-calendar :title="'Release: '.$movie['title']" :start="$movie['release_date']" :all-day="true"
                                :description="$movie['overview']" :url="route('movies.show', $movie['id'])" :key="'calendar-'.$movie['id']" />
                        @endif
                        <a href="{{ route('movies.discussion', $movie['id']) }}"
                           class="inline-flex items-center gap-2 rounded-lg bg-surface-1 px-3 py-2 text-sm font-semibold text-fg ring-1 ring-line transition hover:bg-surface-2 focus-visible:ring-2 focus-visible:ring-cerulean focus-visible:outline-none">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 8.511c.884.284 1.5 1.128 1.5 2.097v4.286c0 1.136-.847 2.1-1.98 2.193-.34.027-.68.052-1.02.072v3.091l-3-3c-1.354 0-2.694-.055-4.02-.163a2.115 2.115 0 0 1-.825-.242m9.345-8.334a2.126 2.126 0 0 0-.476-.095 48.64 48.64 0 0 0-8.048 0c-1.131.094-1.976 1.057-1.976 2.192v4.286c0 .837.46 1.58 1.155 1.951m9.345-8.334V6.637c0-1.621-1.152-3.026-2.76-3.235A48.455 48.455 0 0 0 11.25 3c-2.115 0-4.198.137-6.24.402-1.608.209-2.76 1.614-2.76 3.235v6.226c0 1.621 1.152 3.026 2.76 3.235.577.075 1.157.14 1.74.194V21l4.155-4.155"/></svg>
                            Discussion
                        </a>
                    </div>
                </div>
            </div>
        </section>

        {{-- Overview and director --}}
        <section class="grid gap-8 lg:grid-cols-3">
            <div class="space-y-3 lg:col-span-2">
                <h2 class="text-xl font-bold text-fg">Overview</h2>
                <p class="leading-relaxed wrap-anywhere text-muted">{{ $movie['overview'] ?? 'No overview yet.' }}</p>
            </div>

            @if ($movie['directors'] !== [])
                <div class="space-y-3">
                    <h2 class="text-xl font-bold text-fg">{{ count($movie['directors']) > 1 ? 'Directors' : 'Director' }}</h2>
                    <ul class="space-y-3">
                        @foreach ($movie['directors'] as $director)
                            <li>
                                <a href="{{ route('people.show', $director['id']) }}" class="group flex items-center gap-3">
                                    <x-avatar :name="$director['name']" :src="$director['photo_url']" size="md" />
                                    <span class="font-medium text-fg group-hover:text-fg group-hover:underline">{{ $director['name'] }}</span>
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
                <h2 id="cast-title" class="text-xl font-bold text-fg">Cast</h2>
                <x-movie-row label="Cast">
                    @foreach ($movie['cast'] as $person)
                        <x-movie-row.item wire:key="cast-{{ $person['id'] }}" width="w-28" class="text-center">
                            <a href="{{ route('people.show', $person['id']) }}" class="group block rounded-xl focus-visible:ring-2 focus-visible:ring-cerulean focus-visible:outline-none">
                                <x-avatar :name="$person['name']" :src="$person['photo_url']" size="xl" class="mx-auto transition group-hover:opacity-85" />
                                <p class="mt-2 line-clamp-2 text-sm font-semibold text-fg group-hover:text-fg group-hover:underline">{{ $person['name'] }}</p>
                                @if ($person['character'])
                                    <p class="line-clamp-2 text-xs text-muted">{{ $person['character'] }}</p>
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
                <h2 id="trailer-title" class="text-xl font-bold text-fg">Trailer</h2>
                <div class="aspect-video w-full max-w-4xl overflow-hidden rounded-2xl bg-black ring-1 ring-line" wire:ignore>
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
        <livewire:catalog.similar-movies :movie-id="$movie['id']" :key="'similar-'.$movie['id']" lazy />
    @endif
</div>
