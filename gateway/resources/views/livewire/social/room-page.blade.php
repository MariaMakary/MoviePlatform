<div class="mx-auto max-w-3xl space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
        <a href="{{ route('movies.show', $movieId) }}" class="text-gray-400 hover:text-white hover:underline">&larr; Back to the movie</a>
        <a href="{{ route('rooms.index') }}" class="text-gray-400 hover:text-white hover:underline">All discussions</a>
    </div>

    @if ($this->isUnavailable('room'))
        <x-service-unavailable :service="$unavailable['room']" :retry="true" />
    @elseif ($room)
        <section class="flex flex-col rounded-2xl border border-white/10 bg-white/[0.03]"
                 x-data="{
                     stick: true,
                     previousHeight: 0,
                     toBottom() { requestAnimationFrame(() => this.$refs.list.scrollTop = this.$refs.list.scrollHeight) },
                     nearBottom() { const list = this.$refs.list; return list.scrollHeight - list.scrollTop - list.clientHeight < 80 },
                 }"
                 x-init="toBottom()"
                 x-on:room-message-added.window="if ($event.detail.mine || stick) toBottom()"
                 x-on:room-older-loaded.window="requestAnimationFrame(() => $refs.list.scrollTop += $refs.list.scrollHeight - previousHeight)">
            <header class="border-b border-white/10 px-5 py-4">
                <p class="text-xs font-semibold tracking-wide text-cerulean uppercase">Discussion</p>
                <h1 class="text-xl font-bold tracking-tight text-white">{{ $room['name'] }}</h1>
            </header>

            <div x-ref="list" x-on:scroll.debounce.50ms="stick = nearBottom()" style="overflow-anchor: none"
                 class="h-[60vh] space-y-4 overflow-y-auto px-5 py-4" aria-live="polite" aria-label="Messages">
                @if ($hasMore)
                    <div class="flex flex-col items-center gap-2">
                        <x-button variant="ghost" size="sm" x-on:click="previousHeight = $refs.list.scrollHeight" wire:click="loadOlder"
                                  wire:loading.attr="disabled" wire:target="loadOlder">Load older messages</x-button>
                        @if ($this->isUnavailable('older'))
                            <x-service-unavailable :service="$unavailable['older']" compact />
                        @endif
                    </div>
                @endif

                @forelse ($messages as $message)
                    @php($person = $people[$message['user_id']] ?? null)
                    @php($mine = $message['user_id'] === $userId)
                    <article wire:key="message-{{ $message['id'] }}" class="flex items-start gap-3">
                        <a href="{{ route('users.show', $message['user_id']) }}" class="shrink-0">
                            <x-avatar :name="$person['name'] ?? '?'" :src="$person['avatar'] ?? null" size="sm" />
                        </a>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-baseline gap-x-2">
                                <a href="{{ route('users.show', $message['user_id']) }}" @class(['text-sm font-semibold hover:underline', 'text-neon-rose' => $mine, 'text-white' => ! $mine])>
                                    {{ $mine ? 'You' : ($person['name'] ?? 'Someone') }}
                                </a>
                                <x-date :value="$message['created_at']" format="j M, H:i" class="text-xs text-gray-500" />
                            </div>
                            <p class="text-sm whitespace-pre-line wrap-anywhere text-gray-200">{{ $message['body'] }}</p>
                        </div>
                    </article>
                @empty
                    <x-empty-state title="No messages yet" description="Be the first to say something about this movie." />
                @endforelse
            </div>

            <form wire:submit="send" class="space-y-2 border-t border-white/10 px-5 py-4">
                <div class="flex items-start gap-2">
                    <label for="body" class="sr-only">Your message</label>
                    <input id="body" type="text" wire:model="body" maxlength="{{ $maxBody }}" autocomplete="off"
                           placeholder="Write a message…"
                           @error('body') aria-invalid="true" aria-describedby="body-error" @enderror
                           @class([
                               'block w-full rounded-lg border bg-white/5 px-3 py-2 text-sm text-white placeholder-gray-500 transition focus:ring-[1.5px] focus:ring-neon-rose/75 focus:outline-none',
                               'border-white/10' => ! $errors->has('body'),
                               'border-neon-rose' => $errors->has('body'),
                           ])>
                    <x-button type="submit" wire:loading.attr="disabled" wire:target="send">Send</x-button>
                </div>
                @error('body')
                    <p id="body-error" class="text-sm text-neon-rose">{{ $message }}</p>
                @enderror
                @if ($this->isUnavailable('send'))
                    <x-service-unavailable :service="$unavailable['send']" compact />
                @endif
            </form>
        </section>
    @endif
</div>
