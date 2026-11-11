@props(['width' => 'w-36 sm:w-40 lg:w-44'])

<div role="listitem" {{ $attributes->class(['shrink-0', $width]) }}>
    {{ $slot }}
</div>
