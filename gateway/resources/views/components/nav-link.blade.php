@props(['href', 'active' => false, 'mobile' => false])

<a href="{{ $href }}" {{ $attributes->class([
    'rounded-md px-3 py-2 font-medium transition',
    'text-base' => $mobile,
    'text-sm' => ! $mobile,
    'bg-cerulean text-white' => $active,
    'text-gray-300 hover:bg-white/5 hover:text-white' => ! $active,
]) }} @if ($active) aria-current="page" @endif>{{ $slot }}</a>
