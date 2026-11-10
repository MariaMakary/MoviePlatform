@props(['variant' => 'primary', 'size' => 'md', 'href' => null, 'type' => 'button'])

@php
    $variants = [
        'primary' => 'bg-neon-rose text-jet hover:bg-neon-rose/85',
        'secondary' => 'bg-cerulean text-white hover:bg-cerulean/85',
        'ghost' => 'bg-white/5 text-gray-100 ring-1 ring-white/10 hover:bg-white/10',
        'danger' => 'bg-red-600 text-white hover:bg-red-500',
    ];

    $sizes = [
        'sm' => 'px-3 py-1.5 text-sm',
        'md' => 'px-4 py-2 text-sm',
        'lg' => 'px-5 py-3 text-base',
    ];

    $classes = 'inline-flex items-center justify-center gap-2 rounded-lg font-semibold transition '
        .'focus-visible:ring-2 focus-visible:ring-cerulean focus-visible:ring-offset-2 focus-visible:ring-offset-jet focus-visible:outline-none '
        .'disabled:pointer-events-none disabled:opacity-50 '
        .$variants[$variant].' '.$sizes[$size];
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->class($classes) }}>{{ $slot }}</button>
@endif
