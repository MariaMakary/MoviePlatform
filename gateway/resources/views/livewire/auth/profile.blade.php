<div class="mx-auto max-w-2xl space-y-6">
    <h1 class="text-2xl font-bold tracking-tight text-fg">My profile</h1>

    @if ($this->isUnavailable('identity'))
        <x-service-unavailable :service="$unavailable['identity']" :retry="true" />
    @elseif ($this->isUnavailable('save'))
        <p role="alert" class="rounded-lg border border-neon-rose/40 bg-neon-rose/10 px-3 py-2 text-sm text-pink-100">
            {{ $unavailable['save'] }} isn't responding, so nothing was saved. Try again in a moment.
        </p>
    @endif

    @if ($user)
        <section class="flex items-center gap-4 rounded-2xl border border-line bg-surface-1 p-6">
            <div class="shrink-0">
                <label for="avatar" title="Change photo (jpg or png, up to 2 MB)"
                       class="group relative block cursor-pointer rounded-full focus-within:ring-2 focus-within:ring-cerulean focus-within:ring-offset-2 focus-within:ring-offset-jet">
                    <x-avatar :name="$user['name']" :src="$user['avatar']" size="xl" class="transition group-hover:opacity-80" />
                    <span class="absolute right-0 bottom-0 flex h-8 w-8 items-center justify-center rounded-full bg-cerulean text-white ring-2 ring-jet transition group-hover:scale-110">
                        <x-icons.plus class="h-4 w-4" />
                    </span>
                    <span wire:loading.flex wire:target="avatar" class="absolute inset-0 items-center justify-center rounded-full bg-jet/70 text-xs font-semibold text-fg">
                        Uploading…
                    </span>
                    <span class="sr-only">Change photo</span>
                    <input id="avatar" type="file" wire:model="avatar" accept="image/jpeg,image/png" class="sr-only"
                           @error('avatar') aria-invalid="true" aria-describedby="avatar-error" @enderror>
                </label>
            </div>
            <div class="min-w-0 space-y-1">
                <p class="truncate text-xl font-semibold text-fg">{{ $user['name'] }}</p>
                <p class="truncate text-sm text-muted">{{ $user['email'] }}</p>
                @if ($user['bio'])
                    <p class="text-sm whitespace-pre-line wrap-anywhere text-fg">{{ $user['bio'] }}</p>
                @else
                    <p class="text-sm text-subtle">No bio yet.</p>
                @endif
                @error('avatar') <p id="avatar-error" role="alert" class="text-sm text-neon-rose">{{ $message }}</p> @enderror
            </div>
        </section>

        <form wire:submit="saveProfile" class="space-y-4 rounded-2xl border border-line bg-surface-1 p-6">
            <h2 class="text-lg font-semibold text-fg">Name and bio</h2>

            <x-input label="Name" name="name" wire:model="name" autocomplete="name" maxlength="100" required />

            <div>
                <label for="bio" class="block text-sm font-medium text-fg">Bio</label>
                <textarea id="bio" wire:model="bio" rows="3" maxlength="500" placeholder="A few words about you and the movies you like"
                          @error('bio') aria-invalid="true" aria-describedby="bio-error" @enderror
                          @class(['mt-1 block w-full rounded-lg border bg-surface-1 px-3 py-2 text-sm text-fg placeholder-subtle focus:ring-[1.5px] focus:ring-neon-rose/75 focus:outline-none',
                                  'border-line-strong' => ! $errors->has('bio'), 'border-neon-rose' => $errors->has('bio')])></textarea>
                @error('bio') <p id="bio-error" class="mt-1 text-sm text-neon-rose">{{ $message }}</p> @enderror
            </div>

            <div class="flex justify-end">
                <x-button type="submit" wire:loading.attr="disabled" wire:target="saveProfile">Save</x-button>
            </div>
        </form>

        <livewire:auth.favorite-genres embedded />

        <a href="{{ route('settings.privacy') }}" class="flex items-center justify-between rounded-2xl border border-line bg-surface-1 p-6 transition hover:border-cerulean/60 hover:bg-surface-2">
            <span class="space-y-1">
                <span class="block text-lg font-semibold text-fg">Privacy</span>
                <span class="block text-sm text-muted">Choose what others see on your public profile.</span>
            </span>
            <span aria-hidden="true" class="text-muted">&rarr;</span>
        </a>

        <form wire:submit="changePassword" class="space-y-4 rounded-2xl border border-line bg-surface-1 p-6">
            <h2 class="text-lg font-semibold text-fg">Password</h2>

            <x-input label="Current password" name="current_password" type="password" wire:model="current_password" autocomplete="current-password" />
            <x-input label="New password" name="password" type="password" wire:model="password" autocomplete="new-password" minlength="8" required />
            <x-input label="Confirm new password" name="password_confirmation" type="password" wire:model="password_confirmation" autocomplete="new-password" required />

            <div class="flex flex-wrap items-center justify-between gap-4">
                <a href="{{ route('password.request') }}" class="text-sm text-muted hover:text-fg hover:underline">Signed up with Google or GitHub?</a>
                <x-button type="submit" variant="secondary" wire:loading.attr="disabled" wire:target="changePassword">Change password</x-button>
            </div>
        </form>
    @endif
</div>
