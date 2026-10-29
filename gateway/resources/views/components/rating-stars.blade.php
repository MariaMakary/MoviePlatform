@props(['value' => 0, 'max' => 5, 'size' => 'md'])

@php
    $value = max(0, min((float) $value, $max));
    $percent = $max > 0 ? round($value / $max * 100, 2) : 0;
    $label = rtrim(rtrim(number_format($value, 1), '0'), '.');
    $starSize = ['sm' => 'h-4 w-4', 'md' => 'h-5 w-5', 'lg' => 'h-6 w-6'][$size];
@endphp

<span role="img" aria-label="{{ $label }} out of {{ $max }} stars" {{ $attributes->class('relative inline-flex shrink-0') }}>
    <span class="flex text-white/20" aria-hidden="true">
        @for ($i = 0; $i < $max; $i++)
            <svg class="{{ $starSize }}" viewBox="0 0 20 20" fill="currentColor"><path d="M10 1.5l2.6 5.3 5.9.9-4.3 4.1 1 5.8L10 14.9l-5.2 2.7 1-5.8L1.5 7.7l5.9-.9L10 1.5z"/></svg>
        @endfor
    </span>
    <span class="absolute inset-y-0 left-0 flex overflow-hidden text-neon-rose" style="width: {{ $percent }}%" aria-hidden="true">
        @for ($i = 0; $i < $max; $i++)
            <svg class="{{ $starSize }} shrink-0" viewBox="0 0 20 20" fill="currentColor"><path d="M10 1.5l2.6 5.3 5.9.9-4.3 4.1 1 5.8L10 14.9l-5.2 2.7 1-5.8L1.5 7.7l5.9-.9L10 1.5z"/></svg>
        @endfor
    </span>
</span>
