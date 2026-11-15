<div class="mx-auto max-w-3xl space-y-10">
    <header>
        @if ($this->isUnavailable('identity'))
            <x-service-unavailable :service="$unavailable['identity']" :retry="true" />
        @elseif ($user)
            <div class="flex flex-col gap-5 sm:flex-row sm:items-center">
                <x-avatar :name="$user['name']" :src="$user['avatar']" size="xl" />
                <div class="min-w-0 flex-1 space-y-2">
                    <h1 class="truncate text-3xl font-bold tracking-tight text-white">{{ $user['name'] }}</h1>
                    @if ($user['bio'])
                        <p class="text-sm leading-relaxed whitespace-pre-line text-gray-300">{{ $user['bio'] }}</p>
                    @endif
                    <livewire:social.follow-stats :user-id="$id" :key="'stats-'.$id" />
                </div>
                <div class="shrink-0">
                    @if ($isMe)
                        <x-button variant="ghost" :href="route('profile')">Edit profile</x-button>
                    @else
                        <livewire:social.follow-button :user-id="$id" :key="'follow-'.$id" />
                    @endif
                </div>
            </div>
        @endif
    </header>

    @if ($this->isUnavailable('privacy'))
        <x-service-unavailable :service="$unavailable['privacy']" :retry="true" />
    @endif

    @if ($privacy['show_reviews'] ?? false)
        <section class="space-y-4" aria-labelledby="reviews-heading">
            <h2 id="reviews-heading" class="text-xl font-semibold text-white">
                Reviews
                @if (($reviews['meta']['total'] ?? 0) > 0)
                    <span class="text-sm font-normal text-gray-400">({{ number_format($reviews['meta']['total']) }})</span>
                @endif
            </h2>

            @if ($this->isUnavailable('reviews'))
                <x-service-unavailable :service="$unavailable['reviews']" :retry="true" />
            @elseif (($reviews['data'] ?? []) === [])
                <x-empty-state title="No reviews yet" />
            @else
                <ul class="space-y-4">
                    @foreach ($reviews['data'] as $review)
                        @php($movie = $movies[$review['movie_id']] ?? null)
                        <li wire:key="review-{{ $review['id'] }}" class="rounded-2xl border border-white/10 bg-white/[0.02] p-4">
                            <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                                <a href="{{ route('movies.show', $review['movie_id']) }}" class="font-semibold text-white hover:underline">
                                    {{ $movie['title'] ?? 'A movie' }}
                                </a>
                                @if ($privacy['show_ratings'] && $review['author_score'] !== null)
                                    <x-rating-stars :value="$review['author_score']" size="sm" />
                                @endif
                                <x-date :value="$review['created_at']" format="j M Y" class="text-xs text-gray-400" />
                            </div>
                            <p class="mt-3 text-sm leading-relaxed whitespace-pre-line text-gray-200">{{ $review['body'] }}</p>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    @endif

    @if ($privacy['show_lists'] ?? false)
        <section class="space-y-4" aria-labelledby="lists-heading">
            <h2 id="lists-heading" class="text-xl font-semibold text-white">Lists</h2>

            @if ($this->isUnavailable('lists'))
                <x-service-unavailable :service="$unavailable['lists']" :retry="true" />
            @elseif (($lists['data'] ?? []) === [])
                <x-empty-state title="No public lists yet" />
            @else
                <ul class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($lists['data'] as $list)
                        <li wire:key="list-{{ $list['id'] }}">
                            <a href="{{ route('lists.show', $list['id']) }}"
                               class="flex h-full flex-col gap-2 rounded-2xl border border-white/10 bg-white/[0.03] p-5 transition hover:border-cerulean/60 hover:bg-white/5 focus-visible:ring-2 focus-visible:ring-cerulean focus-visible:outline-none">
                                <h3 class="line-clamp-2 font-semibold text-white">{{ $list['title'] }}</h3>
                                @if ($list['description'])
                                    <p class="line-clamp-3 text-sm text-gray-400">{{ $list['description'] }}</p>
                                @endif
                                <p class="mt-auto pt-2 text-xs text-gray-500">Updated <x-date :value="$list['updated_at']" format="j M Y" /></p>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    @endif
</div>
