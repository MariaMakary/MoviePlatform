<form role="search" class="relative w-full max-w-md" onsubmit="return false">
    <label class="sr-only" for="search-{{ $searchId = uniqid() }}">Search movies</label>
    <svg class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-gray-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35M17 10.5a6.5 6.5 0 1 1-13 0 6.5 6.5 0 0 1 13 0Z"/>
    </svg>
    <input id="search-{{ $searchId }}" type="search" name="q" placeholder="Search movies, people…" autocomplete="off"
           class="w-full rounded-lg border border-white/10 bg-white/5 py-2 pr-3 pl-9 text-sm text-gray-100 placeholder:text-gray-500 focus:border-cerulean focus:ring-2 focus:ring-cerulean/40 focus:outline-none">
</form>
