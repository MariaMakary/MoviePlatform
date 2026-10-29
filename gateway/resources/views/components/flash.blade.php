@php($styles = [
    'success' => 'border-emerald-500/40 bg-emerald-500/10 text-emerald-200',
    'error' => 'border-red-500/40 bg-red-500/10 text-red-200',
    'info' => 'border-cerulean/60 bg-cerulean/15 text-sky-100',
])

@foreach ($styles as $type => $classes)
    @if (session()->has($type))
        <div x-data="{ show: true }" x-show="show" role="{{ $type === 'error' ? 'alert' : 'status' }}"
             class="mb-4 flex items-start justify-between gap-4 rounded-lg border px-4 py-3 text-sm {{ $classes }}">
            <p>{{ session($type) }}</p>
            <button type="button" @click="show = false" class="shrink-0 opacity-70 hover:opacity-100" aria-label="Dismiss">&times;</button>
        </div>
    @endif
@endforeach
