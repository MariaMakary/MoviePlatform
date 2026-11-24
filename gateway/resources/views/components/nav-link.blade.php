@props(['href', 'active' => false, 'mobile' => false])

<a href="{{ $href }}" {{ $attributes->class([
    'rounded-md px-3 py-2 font-medium whitespace-nowrap transition',
    'text-base' => $mobile,
    'text-sm xl:px-2.5 2xl:px-3' => ! $mobile,
    'bg-cerulean text-white' => $active,
    'text-gray-300 hover:bg-white/5 hover:text-white' => ! $active,
]) }} @if ($active) aria-current="page" @endif>{{ $slot }}</a>
