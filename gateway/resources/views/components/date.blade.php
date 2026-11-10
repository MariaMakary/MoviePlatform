@props(['value', 'format' => 'j M Y, H:i'])

@php($date = \App\Services\DisplayTime::fromService($value))

@if ($date)
    <time datetime="{{ $value }}" {{ $attributes }}>{{ $date->translatedFormat($format) }}</time>
@endif
