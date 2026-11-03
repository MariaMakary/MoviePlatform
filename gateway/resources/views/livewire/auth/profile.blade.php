<div class="mx-auto max-w-2xl space-y-6">
    <h1 class="text-2xl font-bold tracking-tight text-white">My profile</h1>

    @if ($this->isUnavailable('identity'))
        <x-service-unavailable :service="$unavailable['identity']" :retry="true" />
    @elseif ($this->isUnavailable('save'))
        <p role="alert" class="rounded-lg border border-neon-rose/40 bg-neon-rose/10 px-3 py-2 text-sm text-pink-100">
            {{ $unavailable['save'] }} isn't responding, so nothing was saved. Try again in a moment.
        </p>
    @endif

    @if ($user)
        <section class="flex items-center gap-4 rounded-2xl border border-white/10 bg-white/[0.03] p-6">
            <div class="shrink-0">
                <label for="avatar" title="Change photo (jpg or png, up to 2 MB)"
                       class="group relative block cursor-pointer rounded-full focus-within:ring-2 focus-within:ring-cerulean focus-within:ring-offset-2 focus-within:ring-offset-jet">
                    <x-avatar :name="$user['name']" :src="$user['avatar']" size="xl" class="transition group-hover:opacity-80" />
                    <span class="absolute right-0 bottom-0 flex h-8 w-8 items-center justify-center rounded-full bg-neon-rose text-jet ring-2 ring-jet transition group-hover:scale-110">
                        <x-icons.plus class="h-4 w-4" />
                    </span>
                    <span wire:loading.flex wire:target="avatar" class="absolute inset-0 items-center justify-center rounded-full bg-jet/70 text-xs font-semibold text-white">
                        Uploading…
                    </span>
                    <span class="sr-only">Change photo</span>
                    <input id="avatar" type="file" wire:model="avatar" accept="image/jpeg,image/png" class="sr-only"
                           @error('avatar') aria-invalid="true" aria-describedby="avatar-error" @enderror>
                </label>
            </div>
            <div class="min-w-0 space-y-1">
                <p class="truncate text-xl font-semibold text-white">{{ $user['name'] }}</p>
                <p class="truncate text-sm text-gray-400">{{ $user['email'] }}</p>
                @if ($user['bio'])
                    <p class="text-sm whitespace-pre-line text-gray-200">{{ $user['bio'] }}</p>
                @else
                    <p class="text-sm text-gray-500">No bio yet.</p>
                @endif
                @error('avatar') <p id="avatar-error" role="alert" class="text-sm text-neon-rose">{{ $message }}</p> @enderror
            </div>
        </section>

        <form wire:submit="saveProfile" class="space-y-4 rounded-2xl border border-white/10 bg-white/[0.03] p-6">
            <h2 class="text-lg font-semibold text-white">Name and bio</h2>

            <x-input label="Name" name="name" wire:model="name" autocomplete="name" maxlength="100" required />

            <div>
                <label for="bio" class="block text-sm font-medium text-gray-200">Bio</label>
                <textarea id="bio" wire:model="bio" rows="3" maxlength="500" placeholder="A few words about you and the movies you like"
                          @error('bio') aria-invalid="true" aria-describedby="bio-error" @enderror
                          @class(['mt-1 block w-full rounded-lg border bg-white/5 px-3 py-2 text-sm text-white placeholder-gray-500 focus:ring-[1.5px] focus:ring-neon-rose/75 focus:outline-none',
                                  'border-white/10' => ! $errors->has('bio'), 'border-neon-rose' => $errors->has('bio')])></textarea>
                @error('bio') <p id="bio-error" class="mt-1 text-sm text-neon-rose">{{ $message }}</p> @enderror
            </div>

            <div class="flex justify-end">
                <x-button type="submit" wire:loading.attr="disabled" wire:target="saveProfile">Save</x-button>
            </div>
        </form>

        <form wire:submit="changePassword" class="space-y-4 rounded-2xl border border-white/10 bg-white/[0.03] p-6">
            <h2 class="text-lg font-semibold text-white">Password</h2>

            <x-input label="Current password" name="current_password" type="password" wire:model="current_password" autocomplete="current-password" />
            <x-input label="New password" name="password" type="password" wire:model="password" autocomplete="new-password" minlength="8" required />
            <x-input label="Confirm new password" name="password_confirmation" type="password" wire:model="password_confirmation" autocomplete="new-password" required />

            <div class="flex items-center justify-between gap-4">
                <a href="{{ route('password.request') }}" class="text-sm text-gray-400 hover:text-white hover:underline">Signed up with Google or GitHub?</a>
                <x-button type="submit" wire:loading.attr="disabled" wire:target="changePassword">Change password</x-button>
            </div>
        </form>
    @endif
</div>
