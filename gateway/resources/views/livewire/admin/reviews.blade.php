@php
    use App\Livewire\Admin\Reviews;

    $selectClasses = 'block w-full rounded-lg border border-white/10 bg-jet px-3 py-2 text-sm text-white focus:ring-[1.5px] focus:ring-neon-rose/75 focus:outline-none';
    $filteredMovie = $movie !== '' ? ($movies[$movie]['title'] ?? 'this movie') : null;
    $confirmations = [
        'hide' => ['Hide this review?', 'It disappears from the movie page and the author\'s profile. You can unhide it later.', 'Hide', 'danger'],
        'unhide' => ['Unhide this review?', 'It shows again on the movie page and the author\'s profile, unless the author is blocked.', 'Unhide', 'primary'],
        'delete' => ['Delete this review?', 'This deletes it for good. The author can write a new one.', 'Delete', 'danger'],
    ];
    $confirmation = $confirmations[$pendingAction ?? ''] ?? null;
@endphp

<x-admin.layout title="Reviews">
    <div class="flex flex-wrap items-end gap-3">
        <div class="w-full sm:w-44">
            <label for="filter-sentiment" class="mb-1 block text-xs font-medium text-gray-400">Sentiment</label>
            <select id="filter-sentiment" wire:model.live="sentiment" class="{{ $selectClasses }}">
                <option value="">All</option>
                @foreach (Reviews::SENTIMENTS as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="w-full sm:w-52">
            <label for="filter-status" class="mb-1 block text-xs font-medium text-gray-400">Status</label>
            <select id="filter-status" wire:model.live="status" class="{{ $selectClasses }}">
                <option value="">All</option>
                @foreach (Reviews::STATUSES as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>

        @if ($filteredMovie)
            <span class="inline-flex items-center gap-2 rounded-full bg-cerulean/15 py-1 pr-1 pl-3 text-sm text-sky-200 ring-1 ring-cerulean/30">
                Movie: <span class="max-w-48 truncate font-medium">{{ $filteredMovie }}</span>
                <button type="button" wire:click="clearMovie" wire:loading.attr="disabled" wire:target="clearMovie" class="rounded-full p-1 hover:bg-white/10" aria-label="Show every movie">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                </button>
            </span>
        @else
            <p class="pb-2 text-xs text-gray-500">Click a movie title to see only its reviews.</p>
        @endif
    </div>

    @if ($notice)
        <p role="status" class="rounded-lg bg-cerulean/10 px-4 py-2 text-sm text-sky-200 ring-1 ring-cerulean/30">{{ $notice }}</p>
    @endif
    @if ($error)
        <p role="alert" class="rounded-lg bg-neon-rose/10 px-4 py-2 text-sm text-pink-200 ring-1 ring-neon-rose/30">{{ $error }}</p>
    @endif

    <div wire:loading.class="opacity-50" class="space-y-4 transition">
        @if ($this->isUnavailable('reviews'))
            <x-service-unavailable :service="$unavailable['reviews']" :retry="true" />
        @elseif ($reviews === [])
            <x-empty-state :title="$filtered ? 'No reviews match these filters' : 'No reviews yet'"
                           :description="$filtered ? 'Try other filters.' : 'Reviews will show up here once members write them.'" />
        @else
            <p class="text-sm text-gray-400">{{ number_format($total) }} {{ Str::plural('review', $total) }}</p>

            <div class="overflow-x-auto rounded-2xl border border-white/10">
                <table class="w-full min-w-[56rem] text-left text-sm">
                    <thead class="border-b border-white/10 bg-white/[0.03] text-xs tracking-wider text-gray-400 uppercase">
                        <tr>
                            <th scope="col" class="px-4 py-3 font-semibold">Review</th>
                            <th scope="col" class="px-4 py-3 font-semibold">Author</th>
                            <th scope="col" class="px-4 py-3 font-semibold">Movie</th>
                            <th scope="col" class="px-4 py-3 font-semibold">Sentiment</th>
                            <th scope="col" class="px-4 py-3 font-semibold">Date</th>
                            <th scope="col" class="px-4 py-3 font-semibold">Status</th>
                            <th scope="col" class="px-4 py-3 text-right font-semibold"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                        @foreach ($reviews as $review)
                            @php
                                $author = $authors[$review['user_id']] ?? null;
                                $movieTitle = $movies[$review['movie_id']]['title'] ?? null;
                            @endphp
                            <tr wire:key="admin-review-{{ $review['id'] }}" class="align-top">
                                <td class="max-w-xs px-4 py-3 text-gray-200">
                                    <p class="line-clamp-3 wrap-anywhere">{{ Str::limit($review['body'], 160) }}</p>
                                </td>
                                <td class="px-4 py-3">
                                    @if ($author)
                                        <a href="{{ route('users.show', $review['user_id']) }}" class="flex items-center gap-2 text-gray-200 hover:text-white hover:underline">
                                            <x-avatar :name="$author['name']" :src="$author['avatar'] ?? null" size="xs" />
                                            <span class="truncate">{{ $author['name'] }}</span>
                                        </a>
                                    @else
                                        <span class="text-gray-500">Unknown member</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <button type="button" wire:click="filterMovie('{{ $review['movie_id'] }}')" wire:loading.attr="disabled" wire:target="filterMovie('{{ $review['movie_id'] }}')" class="text-left text-gray-200 hover:text-white hover:underline"
                                            title="Show only this movie's reviews">
                                        {{ $movieTitle ?? 'Unknown movie' }}
                                    </button>
                                    <a href="{{ route('movies.show', $review['movie_id']) }}" class="block text-xs text-cerulean hover:text-white">Open movie page</a>
                                </td>
                                <td class="px-4 py-3">
                                    @if ($review['sentiment_label'])
                                        @include('livewire.engagement.reviews.sentiment-badge', ['label' => $review['sentiment_label']])
                                    @else
                                        <span class="text-xs text-gray-500">Not analyzed</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-gray-400">
                                    <x-date :value="$review['created_at']" format="j M Y" />
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-col items-start gap-1">
                                        @if ($review['is_hidden'])
                                            <span class="rounded-full bg-neon-rose/15 px-2 py-0.5 text-xs font-medium text-pink-200 ring-1 ring-neon-rose/30">Hidden</span>
                                        @endif
                                        @if ($review['author_blocked'])
                                            <span class="rounded-full bg-white/5 px-2 py-0.5 text-xs font-medium text-gray-300 ring-1 ring-white/15">Author blocked</span>
                                        @endif
                                        @if (! $review['is_hidden'] && ! $review['author_blocked'])
                                            <span class="text-xs text-gray-500">Visible</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex justify-end gap-2">
                                        @if ($review['is_hidden'])
                                            <x-button variant="ghost" size="sm" wire:click="confirm('unhide', '{{ $review['id'] }}')">Unhide</x-button>
                                        @else
                                            <x-button variant="ghost" size="sm" wire:click="confirm('hide', '{{ $review['id'] }}')">Hide</x-button>
                                        @endif
                                        <x-button variant="danger" size="sm" wire:click="confirm('delete', '{{ $review['id'] }}')">Delete</x-button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($lastPage > 1)
                <nav class="flex items-center justify-between text-sm text-gray-400" aria-label="Pagination">
                    <x-button variant="ghost" size="sm" wire:click="goToPage({{ $page - 1 }})" :disabled="$page <= 1">Previous</x-button>
                    <span>Page {{ $page }} of {{ $lastPage }}</span>
                    <x-button variant="ghost" size="sm" wire:click="goToPage({{ $page + 1 }})" :disabled="$page >= $lastPage">Next</x-button>
                </nav>
            @endif
        @endif
    </div>

    <x-modal name="moderate-review" :title="$confirmation[0] ?? 'Confirm'">
        {{ $confirmation[1] ?? '' }}

        <x-slot:footer>
            <x-button variant="ghost" x-on:click="$dispatch('close-modal', 'moderate-review')">Cancel</x-button>
            <x-button :variant="$confirmation[3] ?? 'primary'" wire:click="apply" wire:loading.attr="disabled" wire:target="apply">{{ $confirmation[2] ?? 'Confirm' }}</x-button>
        </x-slot:footer>
    </x-modal>
</x-admin.layout>
