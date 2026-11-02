<div class="mx-auto max-w-md space-y-6 py-6">
    <header class="text-center">
        <h1 class="text-2xl font-bold text-white">Choose a new password</h1>
        @unless ($brokenLink)
            <p class="mt-1 text-sm text-gray-400">For {{ $email }}</p>
        @endunless
    </header>

    @if ($this->isUnavailable('identity'))
        <x-service-unavailable :service="$unavailable['identity']" />
    @endif

    {{-- A link that is incomplete, used or expired: the user needs a new one. Identity puts that error on "token". --}}
    @if ($brokenLink || $errors->hasAny(['token', 'email']))
        <div role="alert" class="space-y-3 rounded-2xl border border-neon-rose/40 bg-neon-rose/10 p-6 text-center">
            <p class="font-semibold text-white">This password reset link is invalid or has expired.</p>
            <p class="text-sm text-pink-100">Links work once, for 60 minutes. Ask for a new one and use the latest email.</p>
            <x-button :href="route('password.request')">Send me a new link</x-button>
        </div>
    @else
        <form wire:submit="resetPassword" class="space-y-4 rounded-2xl border border-white/10 bg-white/[0.03] p-6 shadow-2xl shadow-cerulean/30">
            @error('form')
                <p role="alert" class="rounded-lg border border-neon-rose/40 bg-neon-rose/10 px-3 py-2 text-sm text-pink-100">{{ $message }}</p>
            @enderror

            <x-input label="New password" name="password" type="password" wire:model="password" autocomplete="new-password" minlength="8" required autofocus />
            <x-input label="Confirm new password" name="password_confirmation" type="password" wire:model="password_confirmation" autocomplete="new-password" required />

            <x-button type="submit" class="w-full" wire:loading.attr="disabled">Change password</x-button>
        </form>
    @endif
</div>
