@props(['name', 'src' => null, 'size' => 'md'])

@php
    $sizes = ['xs' => 'h-6 w-6 text-[10px]', 'sm' => 'h-8 w-8 text-xs', 'md' => 'h-10 w-10 text-sm', 'lg' => 'h-16 w-16 text-xl', 'xl' => 'h-24 w-24 text-3xl'];
    $initials = collect(preg_split('/\s+/', trim($name)))->filter()->take(2)->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->join('');
    $background = ['bg-cerulean', 'bg-neon-rose/80', 'bg-cerulean/70', 'bg-neon-rose/60'][abs(crc32($name)) % 4];
@endphp

@if ($src)
    <img src="{{ $src }}" alt="{{ $name }}" {{ $attributes->class(['shrink-0 rounded-full object-cover ring-2 ring-jet', $sizes[$size]]) }}>
@else
    <span role="img" aria-label="{{ $name }}" {{ $attributes->class(['inline-flex shrink-0 items-center justify-center rounded-full font-semibold text-white ring-2 ring-jet select-none', $sizes[$size], $background]) }}>
        <span aria-hidden="true">{{ $initials ?: '?' }}</span>
    </span>
@endif
