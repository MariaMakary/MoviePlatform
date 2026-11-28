<div class="rounded-2xl border border-line bg-surface-1 p-5">
    <p class="text-sm text-muted">{{ $label }}</p>
    @if ($unavailable)
        <x-service-unavailable :service="$unavailable" :retry="true" compact class="mt-2" />
    @else
        <p class="mt-1 text-3xl font-bold text-fg tabular-nums">{{ number_format($value) }}</p>
        @if ($details !== [])
            <p class="mt-1 text-xs text-muted">{{ implode(' · ', $details) }}</p>
        @endif
    @endif
</div>
