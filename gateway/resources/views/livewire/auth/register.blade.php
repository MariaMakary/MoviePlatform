<div class="mx-auto max-w-md space-y-6 py-6">
    <header class="text-center">
        <h1 class="text-2xl font-bold text-white">Create your account</h1>
        <p class="mt-1 text-sm text-gray-400">Rate movies, keep a watchlist and plan movie nights with friends.</p>
    </header>

    @if ($this->isUnavailable('identity'))
        <x-service-unavailable :service="$unavailable['identity']" />
    @endif

    <form wire:submit="register" class="space-y-4 rounded-2xl border border-white/10 bg-white/[0.03] p-6 shadow-2xl shadow-cerulean/30">
        @error('form')
            <p role="alert" class="rounded-lg border border-neon-rose/40 bg-neon-rose/10 px-3 py-2 text-sm text-pink-100">{{ $message }}</p>
        @enderror

        <x-input label="Name" name="name" wire:model="name" autocomplete="name" maxlength="100" required autofocus />
        <x-input label="Email" name="email" type="email" wire:model="email" autocomplete="email" maxlength="255" required />
        <x-input label="Password" name="password" type="password" wire:model="password" autocomplete="new-password" minlength="8" required />
        <x-input label="Confirm password" name="password_confirmation" type="password" wire:model="password_confirmation" autocomplete="new-password" required />

        <x-button type="submit" class="w-full" wire:loading.attr="disabled">Sign up</x-button>
    </form>

    <p class="text-center text-sm text-gray-400">
        Already have an account? <a href="{{ route('login') }}" class="font-semibold text-neon-rose hover:underline">Log in</a>
    </p>
</div>
