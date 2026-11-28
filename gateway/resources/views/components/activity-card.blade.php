{{-- One Social activity entry (feed and profile): "Maria rated Inception ★★★★". $author and $movie may be null when
     Identity or Catalog is down: the card still shows, without the name, title or poster. --}}
@props(['entry', 'author' => null, 'movie' => null])

@php
    $name = $author['name'] ?? 'KAIA member';
    $movieTitle = $movie['title'] ?? 'a movie';
@endphp

<li {{ $attributes->merge(['class' => 'flex gap-4 rounded-2xl border border-line bg-surface-1 p-4']) }}>
    <a href="{{ route('users.show', $entry['user_id']) }}" class="shrink-0" aria-hidden="true" tabindex="-1">
        <x-avatar :name="$name" :src="$author['avatar'] ?? null" size="md" />
    </a>

    <div class="min-w-0 flex-1 space-y-2">
        <p class="text-sm text-muted">
            <a href="{{ route('users.show', $entry['user_id']) }}" class="font-semibold text-fg hover:underline">{{ $name }}</a>
            @switch($entry['type'])
                @case('rating')
                    rated
                    @break
                @case('review')
                    reviewed
                    @break
                @case('watched')
                    watched
                    @break
                @case('list')
                    created the list
                    @break
            @endswitch

            @if ($entry['type'] === 'list')
                <a href="{{ route('lists.show', $entry['subject_id']) }}" class="font-semibold text-cerulean-light hover:underline">{{ $entry['data']['title'] ?? 'a list' }}</a>
            @elseif ($entry['movie_id'])
                <a href="{{ route('movies.show', $entry['movie_id']) }}" class="font-semibold text-cerulean-light hover:underline">{{ $movieTitle }}</a>
            @endif
        </p>

        @if ($entry['type'] === 'rating' && isset($entry['data']['score']))
            <x-rating-stars :value="$entry['data']['score']" size="sm" />
        @elseif ($entry['type'] === 'review' && isset($entry['data']['excerpt']))
            <p class="text-sm leading-relaxed text-fg">“{{ $entry['data']['excerpt'] }}”</p>
        @endif

        <x-date :value="$entry['created_at']" format="j M Y, H:i" class="block text-xs text-subtle" />
    </div>

    @if ($movie && $movie['poster_url'])
        <a href="{{ route('movies.show', $entry['movie_id']) }}" class="shrink-0" aria-hidden="true" tabindex="-1">
            <img src="{{ $movie['poster_url'] }}" alt="" loading="lazy" class="h-24 w-16 rounded-lg object-cover ring-1 ring-line">
        </a>
    @endif
</li>
