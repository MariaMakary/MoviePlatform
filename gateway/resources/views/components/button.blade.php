@props(['variant' => 'primary', 'size' => 'md', 'href' => null, 'type' => 'button'])

@php
    $variants = [
        'primary' => 'bg-neon-rose text-jet hover:bg-neon-rose/85',
        'secondary' => 'bg-cerulean text-white hover:bg-cerulean/85',
        'ghost' => 'bg-surface-1 text-fg ring-1 ring-line hover:bg-surface-2',
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

    $target = $attributes->get('wire:target') ?? $attributes->get('wire:click');
    $target = $target && ! str_starts_with($target, '$dispatch') ? $target : null;

    if ($target) {
        $attributes = $attributes->merge(['wire:target' => $target]);

        if (! $attributes->has('wire:loading.attr')) {
            $attributes = $attributes->merge(['wire:loading.attr' => 'disabled']);
        }
    }
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->class($classes) }}>
        @if ($target)
            <svg wire:loading wire:target="{{ $target }}" class="h-4 w-4 shrink-0 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4Z"/></svg>
        @endif
        {{ $slot }}
    </button>
@endif
