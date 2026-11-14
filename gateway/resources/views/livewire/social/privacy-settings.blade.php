<div class="mx-auto max-w-2xl space-y-6">
    <div class="space-y-2">
        <h1 class="text-2xl font-bold tracking-tight text-white">Privacy settings</h1>
        <p class="text-gray-400">Choose what other people see on your public profile and in their feed. You always see everything yourself.</p>
    </div>

    @if ($this->isUnavailable('privacy'))
        <x-service-unavailable :service="$unavailable['privacy']" :retry="true" />
    @elseif ($filled)
        @if ($this->isUnavailable('save'))
            <p role="alert" class="rounded-lg border border-neon-rose/40 bg-neon-rose/10 px-3 py-2 text-sm text-pink-100">
                {{ $unavailable['save'] }} isn't responding, so nothing was saved. Try again in a moment.
            </p>
        @endif

        <form wire:submit="save" class="space-y-4 rounded-2xl border border-white/10 bg-white/[0.03] p-6">
            @foreach ($labels as $key => [$label, $shown, $hidden])
                <label class="flex cursor-pointer items-start gap-3" wire:key="setting-{{ $key }}">
                    <input type="checkbox" wire:model.live="settings.{{ $key }}" class="mt-0.5 h-4 w-4 rounded border-white/20 bg-white/5 text-cerulean focus:ring-cerulean">
                    <span>
                        <span class="block text-sm font-medium text-gray-200">{{ $label }}</span>
                        <span class="block text-sm text-gray-400">{{ $settings[$key] ? $shown : $hidden }}</span>
                    </span>
                </label>
                @error("settings.$key")
                    <p class="text-sm text-neon-rose">{{ $message }}</p>
                @enderror
            @endforeach

            <div class="flex justify-end">
                <x-button type="submit" wire:loading.attr="disabled" wire:target="save">Save</x-button>
            </div>
        </form>
    @endif
</div>
