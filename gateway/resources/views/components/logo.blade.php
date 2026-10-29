<a href="{{ route('home') }}" {{ $attributes->class('flex shrink-0 items-center gap-2 text-lg font-bold tracking-tight') }}>
    <svg class="h-7 w-7 text-neon-rose" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
        <path d="M4 4h16a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Zm0 2v2h2V6H4Zm14 0v2h2V6h-2ZM4 10v4h2v-4H4Zm14 0v4h2v-4h-2ZM4 16v2h2v-2H4Zm14 0v2h2v-2h-2ZM9 8.5v7l6-3.5-6-3.5Z"/>
    </svg>
    <span>{{ config('app.name') }}</span>
</a>
