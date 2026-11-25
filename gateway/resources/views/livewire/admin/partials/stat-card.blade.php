<div class="rounded-2xl border border-white/10 bg-white/[0.03] p-5">
    <p class="text-sm text-gray-400">{{ $label }}</p>
    @if ($unavailable)
        <x-service-unavailable :service="$unavailable" :retry="true" compact class="mt-2" />
    @else
        <p class="mt-1 text-3xl font-bold text-white tabular-nums">{{ number_format($value) }}</p>
        @if ($details !== [])
            <p class="mt-1 text-xs text-gray-400">{{ implode(' · ', $details) }}</p>
        @endif
    @endif
</div>
