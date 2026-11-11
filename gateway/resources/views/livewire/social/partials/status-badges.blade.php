@use('MoviePlatform\Common\MovieNights\MovieNightStatus')
@use('MoviePlatform\Common\MovieNights\ParticipantStatus')

@php
    $nightStatus = MovieNightStatus::tryFrom($night['status'] ?? '');
    $myStatus = ParticipantStatus::tryFrom($night['my_status'] ?? '');
    $hosting = $night['host_id'] === $userId;
@endphp

<span class="inline-flex flex-wrap items-center gap-1.5">
    @if ($nightStatus === MovieNightStatus::Open)
        <span class="rounded-full bg-cerulean/15 px-2 py-0.5 text-xs font-semibold text-cerulean">Open</span>
    @elseif ($nightStatus === MovieNightStatus::Finalized)
        <span class="rounded-full bg-neon-rose/15 px-2 py-0.5 text-xs font-semibold text-neon-rose">Finalized</span>
    @elseif ($nightStatus === MovieNightStatus::Cancelled)
        <span class="rounded-full bg-white/5 px-2 py-0.5 text-xs font-semibold text-gray-400">Cancelled</span>
    @endif

    @if ($hosting)
        <span class="rounded-full bg-white/5 px-2 py-0.5 text-xs font-medium text-gray-200 ring-1 ring-white/10">Hosting</span>
    @elseif ($myStatus === ParticipantStatus::Accepted)
        <span class="rounded-full bg-white/5 px-2 py-0.5 text-xs font-medium text-gray-200 ring-1 ring-white/10">Accepted</span>
    @elseif ($myStatus === ParticipantStatus::Pending)
        <span class="rounded-full bg-white/5 px-2 py-0.5 text-xs font-medium text-gray-200 ring-1 ring-white/10">Invited</span>
    @elseif ($myStatus === ParticipantStatus::Declined)
        <span class="rounded-full bg-white/5 px-2 py-0.5 text-xs font-medium text-gray-400 ring-1 ring-white/10">Declined</span>
    @endif
</span>
