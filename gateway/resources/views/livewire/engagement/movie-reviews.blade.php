<section id="reviews-section" aria-labelledby="reviews-title" class="space-y-4">
    <div>
        <h2 id="reviews-title" class="text-xl font-bold text-fg sm:text-2xl">Reviews</h2>
        <p class="text-sm text-muted">
            What people think of this movie.@if (! $this->isUnavailable('reviews') && $total > 0) {{ $total }} {{ Str::plural('review', $total) }}.@endif
        </p>
    </div>

    @if ($this->isUnavailable('reviews'))
        <x-service-unavailable :service="$unavailable['reviews']" :retry="true" />
    @else
        @if ($error)
            <p role="alert" class="text-sm text-neon-rose">{{ $error }}</p>
        @endif

        {{-- Writing: only for a logged-in user without a review of this movie --}}
        @if (! $user)
            <p class="rounded-2xl border border-dashed border-line-strong px-4 py-3 text-sm text-muted">
                <button type="button" wire:click="logIn" wire:loading.attr="disabled" wire:target="logIn" class="font-semibold text-cerulean-light hover:underline">Log in</button> to write a review.
            </p>
        @elseif (! $mine)
            <form wire:submit="write" class="space-y-3 rounded-2xl border border-line bg-surface-1 p-4">
                <label for="review-body" class="block text-sm font-medium text-fg">Your review</label>
                <textarea id="review-body" wire:model="body" rows="3" maxlength="2000" required placeholder="What did you think? (10 to 2000 characters)"
                          @class(['block w-full rounded-lg border bg-surface-1 px-3 py-2 text-sm text-fg placeholder-subtle focus:ring-[1.5px] focus:ring-neon-rose/75 focus:outline-none',
                                  'border-line-strong' => ! $errors->hasAny(['body', 'movie_id']), 'border-neon-rose' => $errors->hasAny(['body', 'movie_id'])])></textarea>
                @error('body') <p class="text-sm text-neon-rose">{{ $message }}</p> @enderror
                @error('movie_id') <p class="text-sm text-neon-rose">{{ $message }}</p> @enderror
                <div class="flex justify-end">
                    <x-button type="submit" size="sm" wire:loading.attr="disabled" wire:target="write">Post review</x-button>
                </div>
            </form>
        @endif

        @php
            // Your own review first, then the others (without repeating yours).
            $shown = $mine ? [$mine, ...array_filter($reviews, fn ($review) => $review['id'] !== $mine['id'])] : $reviews;
        @endphp

        @if ($shown === [])
            <x-empty-state title="No reviews yet" description="Be the first to say what you think of this movie." />
        @else
            <ul class="space-y-3">
                @foreach ($shown as $review)
                    @php
                        $author = $authors[$review['user_id']] ?? null;
                        $isMine = $user && $review['user_id'] === $user['id'];
                        $name = $isMine ? $user['name'] : ($author['name'] ?? 'KAIA member');
                        $avatar = $isMine ? $user['avatar'] : ($author['avatar'] ?? null);
                    @endphp
                    <li wire:key="review-{{ $review['id'] }}" @class(['rounded-2xl border p-4', 'border-cerulean/40 bg-cerulean/5' => $isMine, 'border-line bg-surface-1' => ! $isMine])>
                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                            <x-avatar :name="$name" :src="$avatar" size="sm" />
                            <span class="font-semibold text-fg">{{ $name }}@if ($isMine) <span class="font-normal text-muted">(you)</span>@endif</span>
                            @if ($review['author_score'] !== null)
                                <x-rating-stars :value="$review['author_score']" size="sm" />
                            @endif
                            <x-date :value="$review['created_at']" format="j M Y" class="text-xs text-muted" />
                            @include('livewire.engagement.reviews.sentiment-badge', ['label' => $review['sentiment_label']])
                        </div>

                        @if ($isMine && $editing)
                            <form wire:submit="saveEdit('{{ $review['id'] }}')" class="mt-3 space-y-2">
                                <label for="review-edit" class="sr-only">Edit your review</label>
                                <textarea id="review-edit" wire:model="editBody" rows="3" maxlength="2000" required
                                          class="block w-full rounded-lg border border-line bg-surface-1 px-3 py-2 text-sm text-fg focus:ring-[1.5px] focus:ring-neon-rose/75 focus:outline-none"></textarea>
                                @error('editBody') <p class="text-sm text-neon-rose">{{ $message }}</p> @enderror
                                <div class="flex justify-end gap-2">
                                    <x-button variant="ghost" size="sm" wire:click="cancelEditing">Cancel</x-button>
                                    <x-button type="submit" size="sm" wire:loading.attr="disabled" wire:target="saveEdit">Save</x-button>
                                </div>
                            </form>
                        @else
                            <p class="mt-3 text-sm leading-relaxed whitespace-pre-line wrap-anywhere text-fg">{{ $review['body'] }}</p>
                            @if ($isMine)
                                <div class="mt-3 flex gap-2">
                                    <x-button variant="ghost" size="sm" wire:click="startEditing">Edit</x-button>
                                    <x-button variant="ghost" size="sm" wire:click="deleteReview('{{ $review['id'] }}')"
                                              wire:confirm="Delete your review of this movie?">Delete</x-button>
                                </div>
                            @endif
                        @endif
                    </li>
                @endforeach
            </ul>

            @if ($lastPage > 1)
                <nav class="flex items-center justify-between text-sm text-muted" aria-label="Reviews pagination">
                    <x-button variant="ghost" size="sm" wire:click="goToPage({{ $page - 1 }})" :disabled="$page <= 1">Previous</x-button>
                    <span>Page {{ $page }} of {{ $lastPage }}</span>
                    <x-button variant="ghost" size="sm" wire:click="goToPage({{ $page + 1 }})" :disabled="$page >= $lastPage">Next</x-button>
                </nav>
            @endif
        @endif
    @endif
</section>
