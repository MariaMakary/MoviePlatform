<div class="mx-auto max-w-md space-y-6 py-6">
    <header class="text-center">
        <h1 class="text-2xl font-bold text-white">Log in</h1>
        <p class="mt-1 text-sm text-gray-400">Welcome back. Pick up where you left off.</p>
    </header>

    @if ($this->isUnavailable('identity'))
        <x-service-unavailable :service="$unavailable['identity']" />
    @endif

    <form wire:submit="login" class="space-y-4 rounded-2xl border border-white/10 bg-white/[0.03] p-6 shadow-2xl shadow-cerulean/30">
        @error('form')
            <p role="alert" class="rounded-lg border border-neon-rose/40 bg-neon-rose/10 px-3 py-2 text-sm text-pink-100">{{ $message }}</p>
        @enderror

        <x-input label="Email" name="email" type="email" wire:model="email" autocomplete="email" required autofocus />
        <x-input label="Password" name="password" type="password" wire:model="password" autocomplete="current-password" required />

        <x-button type="submit" class="w-full" wire:loading.attr="disabled">Log in</x-button>
    </form>

    <p class="text-center text-sm text-gray-400">
        New here? <a href="{{ route('register') }}" class="font-semibold text-neon-rose hover:underline">Create an account</a>
    </p>
</div>
