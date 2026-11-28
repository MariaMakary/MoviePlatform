@use('MoviePlatform\Common\MovieNights\MovieNightStatus')
@use('MoviePlatform\Common\MovieNights\ParticipantStatus')

@php
    $nightStatus = MovieNightStatus::tryFrom($night['status'] ?? '');
    $myStatus = ParticipantStatus::tryFrom($night['my_status'] ?? '');
    $hosting = $night['host_id'] === $userId;
@endphp

<span class="inline-flex flex-wrap items-center gap-1.5">
    @if ($nightStatus === MovieNightStatus::Open)
        <span class="rounded-full bg-cerulean/15 px-2 py-0.5 text-xs font-semibold text-cerulean-light">Open</span>
    @elseif ($nightStatus === MovieNightStatus::Finalized)
        <span class="rounded-full bg-neon-rose/15 px-2 py-0.5 text-xs font-semibold text-neon-rose">Finalized</span>
    @elseif ($nightStatus === MovieNightStatus::Cancelled)
        <span class="rounded-full bg-surface-1 px-2 py-0.5 text-xs font-semibold text-muted">Cancelled</span>
    @endif

    @if ($hosting)
        <span class="rounded-full bg-surface-1 px-2 py-0.5 text-xs font-medium text-fg ring-1 ring-line">Hosting</span>
    @elseif ($myStatus === ParticipantStatus::Accepted)
        <span class="rounded-full bg-surface-1 px-2 py-0.5 text-xs font-medium text-fg ring-1 ring-line">Accepted</span>
    @elseif ($myStatus === ParticipantStatus::Pending)
        <span class="rounded-full bg-surface-1 px-2 py-0.5 text-xs font-medium text-fg ring-1 ring-line">Invited</span>
    @elseif ($myStatus === ParticipantStatus::Declined)
        <span class="rounded-full bg-surface-1 px-2 py-0.5 text-xs font-medium text-muted ring-1 ring-line">Declined</span>
    @endif
</span>
