@props(['movie', 'href' => null, 'posterSize' => 'w342'])

@php
    $poster = \App\Services\TmdbImage::url($movie['poster_path'] ?? null, $posterSize);
    $year = isset($movie['release_date']) ? substr($movie['release_date'], 0, 4) : null;
    $rating = $movie['vote_average'] ?? null;
    $tag = $href ? 'a' : 'div';
@endphp

<{{ $tag }} @if ($href) href="{{ $href }}" @endif {{ $attributes->class('group block rounded-xl focus-visible:ring-2 focus-visible:ring-cerulean focus-visible:outline-none') }}>
    <div class="relative aspect-2/3 overflow-hidden rounded-xl bg-white/5 ring-1 ring-white/10">
        @if ($poster)
            <img src="{{ $poster }}" alt="" loading="lazy" class="h-full w-full object-cover transition duration-300 group-hover:scale-105">
        @else
            <div class="flex h-full w-full items-center justify-center text-gray-600" aria-hidden="true">
                <svg class="h-12 w-12" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909M3.75 19.5h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Z"/></svg>
            </div>
        @endif

        @if ($rating !== null)
            <span class="absolute top-2 right-2 inline-flex items-center gap-1 rounded-md bg-jet/80 px-1.5 py-0.5 text-xs font-semibold text-white backdrop-blur"
                  aria-label="TMDB rating {{ number_format($rating, 1) }} out of 10">
                <x-icons.star class="h-3 w-3 text-neon-rose" />
                <span aria-hidden="true">{{ number_format($rating, 1) }}</span>
            </span>
        @endif
    </div>

    <div class="mt-2 px-0.5">
        <h3 class="line-clamp-2 text-sm font-semibold text-gray-100 group-hover:text-white">{{ $movie['title'] }}</h3>
        @if ($year)
            <p class="text-xs text-gray-400">{{ $year }}</p>
        @endif
    </div>
</{{ $tag }}>
