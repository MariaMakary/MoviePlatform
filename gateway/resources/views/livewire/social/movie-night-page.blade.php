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
        </section>

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

        <x-reserved id="participants-section" title="Who's coming" subtitle="Everyone invited, with their answer." />   {{-- 1224 --}}
        <x-reserved id="shortlist-section" title="Shortlist and votes" subtitle="Pick the movie together." />          {{-- 1227 --}}
    @endif
</div>
