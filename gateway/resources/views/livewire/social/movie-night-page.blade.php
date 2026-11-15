@use('MoviePlatform\Common\MovieNights\ParticipantStatus')

<div class="mx-auto max-w-3xl space-y-6">
    <a href="{{ route('movie-nights.index') }}" class="text-sm text-gray-400 hover:text-white hover:underline">&larr; My movie nights</a>

    @if ($this->isUnavailable('night'))
        <x-service-unavailable :service="$unavailable['night']" :retry="true" />
    @elseif ($night)
        <section class="space-y-4 rounded-2xl border border-white/10 bg-white/[0.03] p-6">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <h1 class="text-2xl font-bold tracking-tight text-white">{{ $night['title'] }}</h1>
                @include('livewire.social.partials.status-badges', ['night' => $night, 'userId' => $userId])
            </div>

            <dl class="grid gap-3 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-gray-400">When</dt>
                    <dd class="font-medium text-white"><x-date :value="$night['scheduled_at']" format="l j F Y, H:i" /></dd>
                </div>
                <div>
                    <dt class="text-gray-400">Host</dt>
                    <dd class="flex items-center gap-2 font-medium text-white">
                        @if ($host)
                            <x-avatar :name="$host['name']" :src="$host['avatar']" size="xs" />
                            {{ $night['host_id'] === $userId ? 'You' : $host['name'] }}
                        @elseif ($night['host_id'] === $userId)
                            You
                        @else
                            <span class="text-gray-400">Unknown</span>
                        @endif
                    </dd>
                </div>
                @if ($night['location'])
                    <div>
                        <dt class="text-gray-400">Where</dt>
                        <dd class="font-medium text-white">{{ $night['location'] }}</dd>
                    </div>
                @endif
                @if ($night['meeting_link'] && \Illuminate\Support\Str::startsWith($night['meeting_link'], ['https://', 'http://']))
                    <div>
                        <dt class="text-gray-400">Online</dt>
                        <dd><a href="{{ $night['meeting_link'] }}" target="_blank" rel="noopener noreferrer" class="font-medium break-all text-cerulean hover:underline">{{ $night['meeting_link'] }}</a></dd>
                    </div>
                @endif
            </dl>

            @if ($night['description'])
                <p class="text-sm whitespace-pre-line text-gray-200">{{ $night['description'] }}</p>
            @endif

            @if ($canAnswer)
                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-white/10 pt-4">
                    <p class="text-sm text-gray-200">
                        @if ($myStatus === ParticipantStatus::Accepted)
                            You're going. Changed your mind?
                        @elseif ($myStatus === ParticipantStatus::Declined)
                            You declined. You can still join while the night is open.
                        @else
                            Are you coming?
                        @endif
                    </p>
                    <div class="flex gap-2">
                        @unless ($myStatus === ParticipantStatus::Accepted)
                            <x-button size="sm" wire:click="answer('{{ $night['id'] }}', '{{ ParticipantStatus::Accepted->value }}')"
                                      wire:loading.attr="disabled" wire:target="answer">Accept</x-button>
                        @endunless
                        @unless ($myStatus === ParticipantStatus::Declined)
                            <x-button size="sm" variant="ghost" wire:click="answer('{{ $night['id'] }}', '{{ ParticipantStatus::Declined->value }}')"
                                      wire:loading.attr="disabled" wire:target="answer">Decline</x-button>
                        @endunless
                    </div>
                </div>
            @endif

            @if ($answerError)
                <p role="alert" class="rounded-lg border border-neon-rose/40 bg-neon-rose/10 px-3 py-2 text-sm text-pink-100">{{ $answerError }}</p>
            @endif

            @if ($canManage)
                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-white/10 pt-4">
                    <p class="text-sm text-gray-200">
                        @if ($leaders === [])
                            Add a movie to the shortlist to finalize the night.
                        @else
                            Ready? Finalize to close the vote.
                        @endif
                    </p>
                    <div class="flex gap-2">
                        <x-button size="sm" x-data x-on:click="$dispatch('open-modal', 'finalize-night')" :disabled="$leaders === []">Finalize</x-button>
                        <x-button size="sm" variant="ghost" x-data x-on:click="$dispatch('open-modal', 'cancel-night')">Cancel movie night</x-button>
                    </div>
                </div>
            @endif
        </section>

        @if ($cancelled)
            <div role="status" class="rounded-2xl border border-white/10 bg-white/[0.03] px-6 py-5 text-center">
                <p class="font-semibold text-white">This movie night was cancelled.</p>
                <p class="mt-1 text-sm text-gray-400">The host called it off, so there's no voting and nothing to join.</p>
            </div>
        @endif

        @if ($finalized)
            <section aria-labelledby="final-title" class="space-y-5 rounded-2xl border border-neon-rose/30 bg-neon-rose/[0.04] p-6">
                <h2 id="final-title" class="text-xl font-bold text-white sm:text-2xl">The movie</h2>

                @if ($this->isUnavailable('winner'))
                    <x-service-unavailable compact :service="$unavailable['winner']" :retry="true" />
                @elseif ($winner)
                    @php($runtime = $winner['runtime'] ? intdiv($winner['runtime'], 60).'h '.($winner['runtime'] % 60).'m' : null)
                    <div class="flex gap-4">
                        <a href="{{ route('movies.show', $winner['id']) }}" class="w-24 shrink-0 overflow-hidden rounded-xl bg-white/5 ring-1 ring-white/10 sm:w-28">
                            @if ($winner['poster_url'])
                                <img src="{{ $winner['poster_url'] }}" alt="" class="aspect-2/3 h-full w-full object-cover">
                            @else
                                <div class="aspect-2/3 w-full" aria-hidden="true"></div>
                            @endif
                        </a>
                        <div class="min-w-0 space-y-1">
                            <a href="{{ route('movies.show', $winner['id']) }}" class="text-lg font-semibold text-white hover:underline">{{ $winner['title'] }}</a>
                            <p class="text-sm text-gray-300">
                                {{ $winner['year'] ?? '' }}
                                @if ($runtime)
                                    @if ($winner['year']) <span aria-hidden="true">·</span> @endif {{ $runtime }}
                                @endif
                            </p>
                            <p class="text-sm font-medium text-cerulean">Won with {{ $winnerVotes }} {{ \Illuminate\Support\Str::plural('vote', $winnerVotes) }}</p>
                        </div>
                    </div>
                @else
                    <p class="text-sm text-gray-400">The winning movie is no longer in the catalog.</p>
                @endif

                <div class="flex flex-wrap items-center gap-2">
                    @if ($night['meeting_link'] && \Illuminate\Support\Str::startsWith($night['meeting_link'], ['https://', 'http://']))
                        <x-button :href="$night['meeting_link']" target="_blank" rel="noopener noreferrer">Join</x-button>
                    @endif
                    <livewire:intelligence.add-to-calendar
                        :title="$night['title'].($winner ? ': '.$winner['title'] : '')"
                        :start="$night['scheduled_at']"
                        :description="$night['description']"
                        :location="$night['location'] ?? $night['meeting_link']"
                        :url="route('movie-nights.show', $night['id'])"
                        :key="'calendar-night-'.$night['id']" />
                </div>

                <div class="space-y-2">
                    <h3 class="text-sm font-semibold text-gray-200">Going ({{ $attendees->count() }})</h3>
                    @if ($this->isUnavailable('people'))
                        <x-service-unavailable compact :service="$unavailable['people']" />
                    @endif
                    <ul class="flex flex-wrap gap-2">
                        @foreach ($attendees as $attendee)
                            @php($person = $people->get($attendee['user_id']))
                            <li wire:key="attendee-{{ $attendee['user_id'] }}" class="flex items-center gap-2 rounded-full bg-white/5 py-1 pr-3 pl-1 text-sm text-white ring-1 ring-white/10">
                                <x-avatar :name="$person['name'] ?? '?'" :src="$person['avatar'] ?? null" size="xs" />
                                {{ $attendee['user_id'] === $userId ? 'You' : ($person['name'] ?? 'Someone') }}
                            </li>
                        @endforeach
                    </ul>
                </div>
            </section>
        @endif

        @if ($canInvite)
            <form wire:submit="invite" class="space-y-4 rounded-2xl border border-white/10 bg-white/[0.03] p-6">
                <h2 class="text-lg font-semibold text-white">Invite more people</h2>

                @if ($this->isUnavailable('invite'))
                    <p role="alert" class="rounded-lg border border-neon-rose/40 bg-neon-rose/10 px-3 py-2 text-sm text-pink-100">
                        {{ $unavailable['invite'] }} isn't responding, so nobody was invited. Try again in a moment.
                    </p>
                @endif

                @include('livewire.social.partials.user-picker')

                @error('form') <p role="alert" class="text-sm text-neon-rose">{{ $message }}</p> @enderror

                <div class="flex justify-end">
                    <x-button type="submit" wire:loading.attr="disabled" wire:target="invite" :disabled="$invitees === []">Send invitations</x-button>
                </div>
            </form>
        @endif

        @unless ($finalized)
            @include('livewire.social.partials.participants')
        @endunless

        @if ($open)
            <livewire:social.shortlist :night-id="$night['id']" :key="'shortlist-'.$night['id']" />
        @endif

        @if ($canManage)
            <x-modal name="finalize-night" title="Finalize the movie night?">
                <div x-data="{ choice: $wire.entangle('winnerChoice') }" class="space-y-3">
                    @if ($this->isUnavailable('leaders'))
                        <x-service-unavailable compact :service="$unavailable['leaders']" />
                    @elseif (count($leaders) === 1)
                        <p><span class="font-semibold text-white">{{ $leaders[0]['title'] }}</span> wins with {{ $leaders[0]['votes'] }} {{ \Illuminate\Support\Str::plural('vote', $leaders[0]['votes']) }}.</p>
                    @else
                        <p>It's a tie. Pick the movie for the night:</p>
                        <fieldset class="space-y-2">
                            <legend class="sr-only">Tied movies</legend>
                            @foreach ($leaders as $leader)
                                <label wire:key="leader-{{ $leader['id'] }}" class="flex cursor-pointer items-center gap-3 rounded-lg bg-white/5 px-3 py-2 ring-1 ring-white/10 has-[:checked]:ring-cerulean">
                                    <input type="radio" name="winner" value="{{ $leader['id'] }}" x-model="choice" class="text-cerulean focus:ring-cerulean">
                                    <span class="flex-1 text-white">{{ $leader['title'] }}</span>
                                    <span class="text-xs text-gray-400">{{ $leader['votes'] }} {{ \Illuminate\Support\Str::plural('vote', $leader['votes']) }}</span>
                                </label>
                            @endforeach
                        </fieldset>
                    @endif
                    <p class="text-gray-400">Voting closes and everyone going is told which movie won. This can't be undone.</p>

                    <div class="flex justify-end gap-2 pt-2">
                        <x-button variant="ghost" x-on:click="$dispatch('close-modal', 'finalize-night')">Not yet</x-button>
                        <x-button wire:click="finalize" wire:loading.attr="disabled" wire:target="finalize"
                                  x-bind:disabled="{{ $this->isUnavailable('leaders') ? 'true' : (count($leaders) > 1 ? '! choice' : 'false') }}">Finalize</x-button>
                    </div>
                </div>
            </x-modal>

            <x-modal name="cancel-night" title="Cancel this movie night?">
                Everyone invited will see it as cancelled, and nobody can vote or join any more. This can't be undone.

                <x-slot:footer>
                    <x-button variant="ghost" x-on:click="$dispatch('close-modal', 'cancel-night')">Keep it</x-button>
                    <x-button variant="danger" wire:click="cancel" wire:loading.attr="disabled" wire:target="cancel">Cancel movie night</x-button>
                </x-slot:footer>
            </x-modal>
        @endif
    @endif
</div>
