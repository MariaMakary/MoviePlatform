<div class="mx-auto max-w-2xl space-y-6">
    <header class="space-y-3">
        @if ($this->isUnavailable('owner'))
            <x-service-unavailable :service="$unavailable['owner']" :retry="true" compact />
        @elseif ($owner)
            <div class="flex items-center gap-4">
                <x-avatar :name="$owner['name']" :src="$owner['avatar']" size="lg" />
                <div class="min-w-0 space-y-1">
                    <h1 class="truncate text-2xl font-bold tracking-tight text-white">{{ $owner['name'] }}</h1>
                    <livewire:social.follow-stats :user-id="$id" :key="'stats-'.$id" />
                </div>
                <div class="ml-auto">
                    <livewire:social.follow-button :user-id="$id" :key="'follow-owner-'.$id" />
                </div>
            </div>
        @endif

        <nav class="flex gap-2 border-b border-white/10" aria-label="Followers and following">
            @foreach (['followers' => 'Followers', 'following' => 'Following'] as $tab => $label)
                <a href="{{ route('users.'.$tab, $id) }}" @if ($type === $tab) aria-current="page" @endif
                   @class(['-mb-px border-b-2 px-3 py-2 text-sm font-semibold transition',
                           'border-neon-rose text-white' => $type === $tab,
                           'border-transparent text-gray-400 hover:text-white' => $type !== $tab])>{{ $label }}</a>
            @endforeach
        </nav>
    </header>

    @if ($this->isUnavailable('social'))
        <x-service-unavailable :service="$unavailable['social']" :retry="true" />
    @elseif ($this->isUnavailable('identity'))
        <x-service-unavailable :service="$unavailable['identity']" :retry="true" />
    @elseif ($users === [])
        <x-empty-state :title="$type === 'following' ? 'Not following anyone yet' : 'No followers yet'" />
    @else
        <ul class="divide-y divide-white/10">
            @foreach ($users as $user)
                @include('livewire.social.partials.user-row', ['user' => $user])
            @endforeach
        </ul>

        @if ($lastPage > 1)
            <nav class="flex items-center justify-between text-sm text-gray-400" aria-label="Pagination">
                <x-button variant="ghost" size="sm" wire:click="goToPage({{ $page - 1 }})" :disabled="$page <= 1">Previous</x-button>
                <span>Page {{ $page }} of {{ $lastPage }}</span>
                <x-button variant="ghost" size="sm" wire:click="goToPage({{ $page + 1 }})" :disabled="$page >= $lastPage">Next</x-button>
            </nav>
        @endif
    @endif
</div>
