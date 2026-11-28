<div class="mx-auto max-w-2xl space-y-6">
    <h1 class="text-2xl font-bold tracking-tight text-fg">Find people</h1>

    <div>
        <label for="people-search" class="sr-only">Search by name</label>
        <div class="relative">
            <input id="people-search" type="search" wire:model.live.debounce.300ms="q" placeholder="Search by name" maxlength="100" autocomplete="off"
                   class="block w-full rounded-lg border border-line-strong bg-surface-1 py-3 pl-4 pr-10 text-fg placeholder-subtle focus:ring-[1.5px] focus:ring-neon-rose/75 focus:outline-none">
            <x-search-spinner target="q" />
        </div>
    </div>

    @if (! $searching)
        <p class="text-sm text-muted">Type at least 2 letters of a name.</p>
    @elseif ($this->isUnavailable('identity'))
        <x-service-unavailable :service="$unavailable['identity']" :retry="true" />
    @elseif ($users === [])
        <x-empty-state title="Nobody found" description="Try another part of the name." />
    @else
        @if ($this->isUnavailable('follow'))
            <x-service-unavailable :service="$unavailable['follow']" :retry="true" compact />
        @endif

        <ul class="divide-y divide-line" wire:loading.class="opacity-60">
            @foreach ($users as $user)
                @include('livewire.social.partials.user-row', ['user' => $user, 'following' => $following])
            @endforeach
        </ul>
    @endif
</div>
