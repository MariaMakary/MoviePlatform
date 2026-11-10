{{-- Space kept for a feature another task fills in. Replace the whole tag with that feature's component. --}}
@props(['id', 'title' => null, 'subtitle' => null, 'compact' => false])

@if ($compact)
    <span id="{{ $id }}" {{ $attributes->class('inline-flex items-center rounded-lg border border-dashed border-white/15 px-3 py-2 text-sm text-gray-500') }}>
        {{ $slot->isEmpty() ? $title : $slot }} · soon
    </span>
@else
    <section id="{{ $id }}" @if ($title) aria-labelledby="{{ $id }}-title" @endif {{ $attributes->class('space-y-4') }}>
        @if ($title)
            <div>
                <h2 id="{{ $id }}-title" class="text-xl font-bold text-white sm:text-2xl">{{ $title }}</h2>
                @if ($subtitle)
                    <p class="text-sm text-gray-400">{{ $subtitle }}</p>
                @endif
            </div>
        @endif

        <div class="flex items-center justify-center rounded-2xl border border-dashed border-white/15 px-6 py-10 text-sm text-gray-500">
            {{ $slot->isEmpty() ? 'Coming soon' : $slot }}
        </div>
    </section>
@endif
