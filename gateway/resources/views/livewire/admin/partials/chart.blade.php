<section class="rounded-2xl border border-white/10 bg-white/[0.03] p-5" aria-labelledby="chart-{{ $id }}-title">
    <h2 id="chart-{{ $id }}-title" class="text-sm font-semibold text-white">{{ $title }}</h2>
    @if ($unavailable)
        <x-service-unavailable :service="$unavailable" :retry="true" class="mt-3" />
    @elseif (array_sum($chart['values']) === 0)
        <p class="mt-3 rounded-xl border border-dashed border-white/15 px-4 py-10 text-center text-sm text-gray-400">{{ $empty }}</p>
    @else
        <div wire:key="chart-{{ $id }}" wire:ignore x-data x-init="window.mountAdminChart($el)"
             data-chart="{{ json_encode($chart) }}" class="relative mt-3 h-64" role="img" aria-label="{{ $title }}">
            <canvas></canvas>
        </div>
    @endif
</section>
