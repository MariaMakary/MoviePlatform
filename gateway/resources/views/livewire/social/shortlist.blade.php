<section id="shortlist-section" aria-labelledby="shortlist-title" class="space-y-4">
    <div class="flex flex-wrap items-end justify-between gap-2">
        <div>
            <h2 id="shortlist-title" class="text-xl font-bold text-white sm:text-2xl">Shortlist and votes</h2>
            <p class="text-sm text-gray-400">Pick the movie together. Votes update live for everyone.</p>
        </div>
        @if ($night)
            <p class="text-sm text-gray-400">{{ $votesCast }} {{ \Illuminate\Support\Str::plural('vote', $votesCast) }} cast</p>
        @endif
    </div>

    @if ($actionError)
        <p role="alert" class="rounded-lg border border-neon-rose/40 bg-neon-rose/10 px-3 py-2 text-sm text-pink-100">{{ $actionError }}</p>
    @endif

    @if ($this->isUnavailable('shortlist'))
        <x-service-unavailable :service="$unavailable['shortlist']" :retry="true" />
    @elseif ($night)
        @if ($canVote)
            <div class="space-y-3 rounded-2xl border border-white/10 bg-white/[0.03] p-4">
                <div>
                    <label for="shortlist-search" class="block text-sm font-medium text-gray-200">Add a movie</label>
                    <div class="relative mt-1">
                        <input id="shortlist-search" type="search" wire:model.live.debounce.300ms="search" x-on:keydown.enter.prevent autocomplete="off" maxlength="100"
                               placeholder="Search by title"
                               class="block w-full rounded-lg border border-white/10 bg-white/5 py-2 pl-3 pr-10 text-sm text-white placeholder-gray-500 transition focus:ring-[1.5px] focus:ring-neon-rose/75 focus:outline-none">
                        <x-search-spinner target="search" />
                    </div>
                    <p class="mt-1 text-xs text-gray-500">Type at least {{ \App\Livewire\Social\Shortlist::MIN_SEARCH_LENGTH }} letters of the title.</p>
                </div>

                @if ($this->isUnavailable('search'))
                    <x-service-unavailable compact :service="$unavailable['search']" />
                @elseif (mb_strlen(trim($search)) >= \App\Livewire\Social\Shortlist::MIN_SEARCH_LENGTH)
                    <ul class="divide-y divide-white/5 rounded-lg border border-white/10 bg-white/[0.02]" wire:loading.class="opacity-60" wire:target="search">
                        @forelse ($results as $movie)
                            <li wire:key="result-{{ $movie['id'] }}">
                                <button type="button" wire:click="add('{{ $movie['id'] }}')" wire:loading.attr="disabled" wire:target="add"
                                        class="flex w-full items-center gap-3 px-3 py-2 text-left text-sm text-gray-100 hover:bg-white/5 focus-visible:bg-white/5 focus-visible:outline-none disabled:opacity-50">
                                    <span class="h-12 w-8 shrink-0 overflow-hidden rounded bg-white/5 ring-1 ring-white/10">
                                        @if ($movie['poster_url'] ?? null)
                                            <img src="{{ $movie['poster_url'] }}" alt="" loading="lazy" class="h-full w-full object-cover">
                                        @endif
                                    </span>
                                    <span class="min-w-0 flex-1 truncate">
                                        {{ $movie['title'] }}
                                        @if ($movie['year'] ?? null)
                                            <span class="text-gray-400">({{ $movie['year'] }})</span>
                                        @endif
                                    </span>
                                    <x-icons.plus class="h-4 w-4 text-cerulean" />
                                </button>
                            </li>
                        @empty
                            <li class="px-3 py-2 text-sm text-gray-400">No movie found, or it is already on the shortlist.</li>
                        @endforelse
                    </ul>
                @endif
            </div>
        @elseif ($open)
            <p class="text-sm text-gray-400">Accept the invitation to propose movies and vote.</p>
        @endif

        @if ($this->isUnavailable('cards'))
            <x-service-unavailable compact :service="$unavailable['cards']" />
        @endif

        @if ($candidates === [])
            <x-empty-state title="No movies yet" description="Movies proposed for the night show up here with their votes." />
        @else
            <ul class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4">
                @foreach ($candidates as $candidate)
                    @php
                        $mine = $candidate['id'] === $myVote;
                        $share = $votesCast > 0 ? (int) round($candidate['votes'] / $votesCast * 100) : 0;
                        $title = $candidate['card']['title'] ?? 'Unknown movie';
                    @endphp
                    <li wire:key="candidate-{{ $candidate['id'] }}"
                        @class(['flex flex-col gap-2 rounded-2xl p-2 ring-1 transition', 'bg-cerulean/10 ring-cerulean' => $mine, 'bg-white/[0.02] ring-white/10' => ! $mine])>
                        @if ($candidate['card'])
                            <x-movie-card :movie="$candidate['card']" :href="route('movies.show', $candidate['card']['id'])" />
                        @else
                            <div class="flex aspect-2/3 items-center justify-center rounded-xl bg-white/5 px-2 text-center text-sm text-gray-400 ring-1 ring-white/10">{{ $title }}</div>
                        @endif

                        <div class="space-y-1 px-0.5">
                            <div class="flex items-center justify-between text-xs">
                                <span @class(['font-semibold', 'text-cerulean' => $mine, 'text-gray-300' => ! $mine])>
                                    {{ $candidate['votes'] }} {{ \Illuminate\Support\Str::plural('vote', $candidate['votes']) }}
                                </span>
                                @if ($mine)
                                    <span class="font-semibold text-cerulean">Your vote</span>
                                @endif
                            </div>
                            <div class="h-2 overflow-hidden rounded-full bg-white/10" role="progressbar" aria-label="Votes for {{ $title }}"
                                 aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $share }}">
                                <div class="h-full rounded-full bg-cerulean transition-all duration-500" style="width: {{ $share }}%"></div>
                            </div>
                        </div>

                        @if ($canVote || $candidate['can_remove'])
                            <div class="mt-auto flex gap-2">
                                @if ($canVote)
                                    <x-button size="sm" class="flex-1" :variant="$mine ? 'secondary' : 'primary'" :disabled="$mine"
                                              wire:click="vote('{{ $candidate['id'] }}')" wire:loading.attr="disabled" wire:target="vote, remove"
                                              aria-label="{{ $mine ? 'You voted for '.$title : 'Vote for '.$title }}">
                                        {{ $mine ? 'Voted' : 'Vote' }}
                                    </x-button>
                                @endif
                                @if ($candidate['can_remove'])
                                    <x-button size="sm" variant="ghost" wire:click="remove('{{ $candidate['id'] }}')" wire:loading.attr="disabled" wire:target="vote, remove"
                                              wire:confirm="Remove {{ $title }} from the shortlist? Its votes are removed too." aria-label="Remove {{ $title }}">
                                        Remove
                                    </x-button>
                                @endif
                            </div>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    @endif
</section>
