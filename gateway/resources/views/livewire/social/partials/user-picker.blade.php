<div class="space-y-3">
    <div>
        <label for="invite-search" class="block text-sm font-medium text-gray-200">Invite people</label>
        <input id="invite-search" type="search" wire:model.live.debounce.300ms="search" x-on:keydown.enter.prevent autocomplete="off" maxlength="100"
               placeholder="Search by name" @disabled(count($invitees) >= $maxInvitees)
               @error('invitees') aria-invalid="true" aria-describedby="invitees-error" @enderror
               class="mt-1 block w-full rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-sm text-white placeholder-gray-500 transition focus:ring-[1.5px] focus:ring-neon-rose/75 focus:outline-none disabled:opacity-50">
        <p class="mt-1 text-xs text-gray-500">
            {{ count($invitees) >= $maxInvitees ? "You can invite up to {$maxInvitees} people at a time." : 'Type at least 2 letters of their name.' }}
        </p>
    </div>

    @if ($this->isUnavailable('search'))
        <x-service-unavailable compact :service="$unavailable['search']" />
    @elseif (mb_strlen(trim($search)) >= 2)
        <ul class="divide-y divide-white/5 rounded-lg border border-white/10 bg-white/[0.02]" wire:loading.class="opacity-60" wire:target="search">
            @forelse ($results as $user)
                <li wire:key="result-{{ $user['id'] }}">
                    <button type="button" wire:click="addInvitee('{{ $user['id'] }}')"
                            class="flex w-full items-center gap-3 px-3 py-2 text-left text-sm text-gray-100 hover:bg-white/5 focus-visible:bg-white/5 focus-visible:outline-none">
                        <x-avatar :name="$user['name']" :src="$user['avatar'] ?? null" size="sm" />
                        <span class="min-w-0 flex-1 truncate">{{ $user['name'] }}</span>
                        <x-icons.plus class="h-4 w-4 text-cerulean" />
                    </button>
                </li>
            @empty
                <li class="px-3 py-2 text-sm text-gray-400">Nobody found.</li>
            @endforelse
        </ul>
    @endif

    @if ($invitees !== [])
        <ul class="flex flex-wrap gap-2" aria-label="People to invite">
            @foreach ($invitees as $user)
                <li wire:key="invitee-{{ $user['id'] }}" class="inline-flex items-center gap-2 rounded-full bg-white/5 py-1 pr-1 pl-1 text-sm text-gray-100 ring-1 ring-white/10">
                    <x-avatar :name="$user['name']" :src="$user['avatar']" size="xs" />
                    <span>{{ $user['name'] }}</span>
                    <button type="button" wire:click="removeInvitee('{{ $user['id'] }}')" aria-label="Remove {{ $user['name'] }}"
                            class="flex h-6 w-6 items-center justify-center rounded-full text-gray-400 hover:bg-white/10 hover:text-white">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                    </button>
                </li>
            @endforeach
        </ul>
    @endif

    @error('invitees') <p id="invitees-error" class="text-sm text-neon-rose">{{ $message }}</p> @enderror
    @error('user_ids') <p class="text-sm text-neon-rose">{{ $message }}</p> @enderror
</div>
