{{-- A review's sentiment from Intelligence ($label: positive, neutral, negative or null). Nothing until it is analyzed. --}}
@php
    $badge = [
        'positive' => ['Positive', 'bg-cerulean/15 text-cerulean-light ring-cerulean/30'],
        'neutral' => ['Neutral', 'bg-surface-1 text-muted ring-line-strong'],
        'negative' => ['Negative', 'bg-neon-rose/15 text-pink-200 ring-neon-rose/30'],
    ][$label ?? ''] ?? null;
@endphp

@if ($badge)
    <span class="rounded-full px-2 py-0.5 text-xs font-medium ring-1 {{ $badge[1] }}">{{ $badge[0] }}</span>
@endif
