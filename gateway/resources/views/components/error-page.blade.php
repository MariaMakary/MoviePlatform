@props(['code', 'title', 'message'])

<div class="mx-auto flex max-w-xl flex-col items-center py-16 text-center sm:py-24">
    <p class="text-6xl font-bold tracking-tight text-neon-rose sm:text-7xl">{{ $code }}</p>
    <h1 class="mt-4 text-2xl font-bold tracking-tight text-white sm:text-3xl">{{ $title }}</h1>
    <p class="mt-3 text-gray-400 wrap-anywhere">{{ $message }}</p>

    <div class="mt-8 flex flex-wrap justify-center gap-3">
        {{ $slot }}
        <x-button :href="route('home')">Back to home</x-button>
    </div>
</div>
