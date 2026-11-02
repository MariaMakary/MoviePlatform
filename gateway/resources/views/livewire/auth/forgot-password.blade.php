<div class="mx-auto max-w-md space-y-6 py-6">
    <header class="text-center">
        <h1 class="text-2xl font-bold text-white">Forgot your password?</h1>
        <p class="mt-1 text-sm text-gray-400">Enter your email and we will send you a link to choose a new one.</p>
    </header>

    @if ($this->isUnavailable('identity'))
        <x-service-unavailable :service="$unavailable['identity']" />
    @endif

    @if ($sent)
        <div role="status" class="space-y-2 rounded-2xl border border-white/10 bg-white/[0.03] p-6 text-center shadow-2xl shadow-cerulean/30">
            <p class="font-semibold text-white">If this email exists, we sent you a link.</p>
            <p class="text-sm text-gray-400">Check your inbox. The link works once, for 60 minutes.</p>
        </div>
    @else
        <form wire:submit="send" class="space-y-4 rounded-2xl border border-white/10 bg-white/[0.03] p-6 shadow-2xl shadow-cerulean/30">
            @error('form')
                <p role="alert" class="rounded-lg border border-neon-rose/40 bg-neon-rose/10 px-3 py-2 text-sm text-pink-100">{{ $message }}</p>
            @enderror

            <x-input label="Email" name="email" type="email" wire:model="email" autocomplete="email" maxlength="255" required autofocus />

            <x-button type="submit" class="w-full" wire:loading.attr="disabled">Send me a link</x-button>
        </form>
    @endif

    <p class="text-center text-sm text-gray-400">
        Remembered it? <a href="{{ route('login') }}" class="font-semibold text-neon-rose hover:underline">Log in</a>
    </p>
</div>
