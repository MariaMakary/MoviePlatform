<div class="mx-auto max-w-2xl space-y-6">
    <div class="space-y-1">
        <a href="{{ route('movie-nights.index') }}" class="text-sm text-muted hover:text-fg hover:underline">&larr; My movie nights</a>
        <h1 class="text-2xl font-bold tracking-tight text-fg">New movie night</h1>
    </div>

    @if ($this->isUnavailable('save'))
        <p role="alert" class="rounded-lg border border-neon-rose/40 bg-neon-rose/10 px-3 py-2 text-sm text-pink-100">
            {{ $unavailable['save'] }} isn't responding, so the movie night wasn't created. Try again in a moment.
        </p>
    @endif

    <form wire:submit="save" class="space-y-4 rounded-2xl border border-line bg-surface-1 p-6">
        <x-input label="Title" name="title" wire:model="title" maxlength="100" placeholder="Friday sci-fi" required />

        <div>
            <label for="description" class="block text-sm font-medium text-fg">Description <span class="text-subtle">(optional)</span></label>
            <textarea id="description" wire:model="description" rows="3" maxlength="2000" placeholder="Snacks, theme, what to bring…"
                      @error('description') aria-invalid="true" aria-describedby="description-error" @enderror
                      @class(['mt-1 block w-full rounded-lg border bg-surface-1 px-3 py-2 text-sm text-fg placeholder-subtle focus:ring-[1.5px] focus:ring-neon-rose/75 focus:outline-none',
                              'border-line-strong' => ! $errors->has('description'), 'border-neon-rose' => $errors->has('description')])></textarea>
            @error('description') <p id="description-error" class="mt-1 text-sm text-neon-rose">{{ $message }}</p> @enderror
        </div>

        <x-input label="Date and time" name="scheduled_at" type="datetime-local" wire:model="scheduled_at" :min="$minDate" required />

        <div class="grid gap-4 sm:grid-cols-2">
            <x-input label="Location" name="location" wire:model="location" maxlength="255" placeholder="Maria's place" />
            <x-input label="Meeting link" name="meeting_link" type="url" wire:model="meeting_link" maxlength="255" placeholder="https://meet.example.com/…" />
        </div>
        <p class="-mt-2 text-xs text-subtle">Give a location, a meeting link, or both.</p>

        <div class="border-t border-line pt-4">
            @include('livewire.social.partials.user-picker')
        </div>

        @error('form') <p role="alert" class="text-sm text-neon-rose">{{ $message }}</p> @enderror

        <div class="flex justify-end gap-2">
            <x-button :href="route('movie-nights.index')" variant="ghost">Cancel</x-button>
            <x-button type="submit" wire:loading.attr="disabled" wire:target="save">Create movie night</x-button>
        </div>
    </form>
</div>
