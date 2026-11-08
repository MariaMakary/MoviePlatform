<div class="mx-auto max-w-xl space-y-6">
    <div>
        <a href="{{ $listId ? route('lists.show', $listId) : route('lists.index') }}" class="text-sm text-gray-400 hover:text-white">&larr; {{ $listId ? 'Back to the list' : 'My lists' }}</a>
        <h1 class="mt-2 text-2xl font-bold tracking-tight text-white">{{ $listId ? 'Edit list' : 'New list' }}</h1>
    </div>

    <form wire:submit="save" class="space-y-5 rounded-2xl border border-white/10 bg-white/[0.03] p-5">
        @error('form')
            <p role="alert" class="text-sm text-neon-rose">{{ $message }}</p>
        @enderror

        <x-input label="Title" name="title" wire:model="title" maxlength="150" required placeholder="Best of Nolan" />

        <div>
            <label for="description" class="block text-sm font-medium text-gray-200">Description <span class="font-normal text-gray-500">(optional)</span></label>
            <textarea id="description" wire:model="description" rows="3" maxlength="500" placeholder="What ties these movies together?"
                      @error('description') aria-invalid="true" aria-describedby="description-error" @enderror
                      @class(['mt-1 block w-full rounded-lg border bg-white/5 px-3 py-2 text-sm text-white placeholder-gray-500 transition focus:ring-[1.5px] focus:ring-neon-rose/75 focus:outline-none',
                              'border-white/10' => ! $errors->has('description'), 'border-neon-rose' => $errors->has('description')])></textarea>
            @error('description')
                <p id="description-error" class="mt-1 text-sm text-neon-rose">{{ $message }}</p>
            @enderror
        </div>

        <label class="flex cursor-pointer items-start gap-3">
            <input type="checkbox" wire:model="isPublic" class="mt-0.5 h-4 w-4 rounded border-white/20 bg-white/5 text-cerulean focus:ring-cerulean">
            <span>
                <span class="block text-sm font-medium text-gray-200">Public</span>
                <span class="block text-sm text-gray-400">Anyone can see a public list. A private list is only for you.</span>
            </span>
        </label>
        @error('is_public')
            <p class="text-sm text-neon-rose">{{ $message }}</p>
        @enderror

        <div class="flex justify-end gap-2">
            <x-button variant="ghost" :href="$listId ? route('lists.show', $listId) : route('lists.index')">Cancel</x-button>
            <x-button type="submit" wire:loading.attr="disabled" wire:target="save">{{ $listId ? 'Save' : 'Create list' }}</x-button>
        </div>
    </form>
</div>
