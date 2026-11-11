@use('MoviePlatform\Common\MovieNights\ParticipantStatus')

<section id="participants-section" aria-labelledby="participants-title" class="space-y-3">
    <div class="flex flex-wrap items-end justify-between gap-2">
        <h2 id="participants-title" class="text-xl font-bold text-white sm:text-2xl">Who's coming</h2>
        <p class="text-sm text-gray-400">
            {{ $counts->get(ParticipantStatus::Accepted->value, 0) }} going
            · {{ $counts->get(ParticipantStatus::Pending->value, 0) }} pending
            · {{ $counts->get(ParticipantStatus::Declined->value, 0) }} declined
        </p>
    </div>

    @if ($this->isUnavailable('people'))
        <x-service-unavailable compact :service="$unavailable['people']" />
    @endif

    <ul class="divide-y divide-white/5 rounded-2xl border border-white/10 bg-white/[0.02]">
        @foreach ($participants as $participant)
            @php
                $person = $people->get($participant['user_id']);
                $name = $participant['user_id'] === $userId ? 'You' : ($person['name'] ?? 'Someone');
                $status = ParticipantStatus::tryFrom($participant['status']);
            @endphp
            <li wire:key="participant-{{ $participant['user_id'] }}" class="flex items-center gap-3 px-4 py-3">
                <x-avatar :name="$person['name'] ?? '?'" :src="$person['avatar'] ?? null" size="sm" />
                <span class="min-w-0 flex-1 truncate text-sm text-white">
                    {{ $name }}
                    @if ($participant['is_host'])
                        <span class="ml-1 rounded-full bg-neon-rose/15 px-2 py-0.5 text-xs font-semibold text-neon-rose">Host</span>
                    @endif
                </span>
                @if ($status === ParticipantStatus::Accepted)
                    <span class="rounded-full bg-cerulean/15 px-2 py-0.5 text-xs font-semibold text-cerulean">Accepted</span>
                @elseif ($status === ParticipantStatus::Pending)
                    <span class="rounded-full bg-white/5 px-2 py-0.5 text-xs font-medium text-gray-300 ring-1 ring-white/10">Pending</span>
                @elseif ($status === ParticipantStatus::Declined)
                    <span class="rounded-full bg-white/5 px-2 py-0.5 text-xs font-medium text-gray-500 line-through ring-1 ring-white/10">Declined</span>
                @endif
            </li>
        @endforeach
    </ul>
</section>
