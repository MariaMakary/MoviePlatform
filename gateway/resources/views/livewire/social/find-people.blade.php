<div class="mx-auto max-w-2xl space-y-6">
    <h1 class="text-2xl font-bold tracking-tight text-white">Find people</h1>

    <div>
        <label for="people-search" class="sr-only">Search by name</label>
        <input id="people-search" type="search" wire:model.live.debounce.300ms="q" placeholder="Search by name" maxlength="100" autocomplete="off"
               class="block w-full rounded-lg border border-white/10 bg-white/5 px-4 py-3 text-white placeholder-gray-500 focus:ring-[1.5px] focus:ring-neon-rose/75 focus:outline-none">
    </div>

    @if (! $searching)
        <p class="text-sm text-gray-400">Type at least 2 letters of a name.</p>
    @elseif ($this->isUnavailable('identity'))
        <x-service-unavailable :service="$unavailable['identity']" :retry="true" />
    @elseif ($users === [])
        <x-empty-state title="Nobody found" description="Try another part of the name." />
    @else
        <ul class="divide-y divide-white/10" wire:loading.class="opacity-60">
            @foreach ($users as $user)
                @include('livewire.social.partials.user-row', ['user' => $user])
            @endforeach
        </ul>
    @endif
</div>
