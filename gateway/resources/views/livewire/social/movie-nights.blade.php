<div class="mx-auto max-w-3xl space-y-6">
    <div class="flex items-center justify-between gap-4">
        <h1 class="text-2xl font-bold tracking-tight text-white">My movie nights</h1>
        <x-button :href="route('movie-nights.create')">
            <x-icons.plus class="h-4 w-4" />
            New movie night
        </x-button>
    </div>

    @if ($this->isUnavailable('nights'))
        <x-service-unavailable :service="$unavailable['nights']" :retry="true" />
    @elseif ($upcoming->isEmpty() && $past->isEmpty())
        <x-empty-state title="No movie nights yet" description="Plan one and invite your friends, or wait for an invitation.">
            <x-button :href="route('movie-nights.create')">Plan a movie night</x-button>
        </x-empty-state>
    @else
        <section aria-labelledby="upcoming-title" class="space-y-3">
            <h2 id="upcoming-title" class="text-lg font-semibold text-white">Upcoming</h2>
            @forelse ($upcoming as $night)
                @include('livewire.social.partials.night-row', ['night' => $night, 'muted' => false])
            @empty
                <p class="rounded-2xl border border-dashed border-white/15 px-6 py-6 text-center text-sm text-gray-400">Nothing planned yet.</p>
            @endforelse
        </section>

        @if ($past->isNotEmpty())
            <section aria-labelledby="past-title" class="space-y-3">
                <h2 id="past-title" class="text-lg font-semibold text-white">Past</h2>
                @foreach ($past as $night)
                    @include('livewire.social.partials.night-row', ['night' => $night, 'muted' => true])
                @endforeach
            </section>
        @endif

        @if ($lastPage > 1)
            <nav class="flex items-center justify-between text-sm text-gray-400" aria-label="Pagination">
                <x-button variant="ghost" size="sm" wire:click="goToPage({{ $page - 1 }})" :disabled="$page <= 1">Previous</x-button>
                <span>Page {{ $page }} of {{ $lastPage }}</span>
                <x-button variant="ghost" size="sm" wire:click="goToPage({{ $page + 1 }})" :disabled="$page >= $lastPage">Next</x-button>
            </nav>
        @endif
    @endif
</div>
