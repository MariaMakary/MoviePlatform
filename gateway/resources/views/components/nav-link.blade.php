@props(['href', 'active' => false, 'mobile' => false])

<a href="{{ $href }}" {{ $attributes->class([
    'rounded-md px-3 py-2 font-medium whitespace-nowrap transition',
    'text-base' => $mobile,
    'text-sm xl:px-2.5 2xl:px-3' => ! $mobile,
    'bg-cerulean text-white' => $active,
    'text-muted hover:bg-surface-2 hover:text-fg' => ! $active,
]) }} @if ($active) aria-current="page" @endif>{{ $slot }}</a>
