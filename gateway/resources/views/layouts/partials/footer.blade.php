<footer class="border-t border-white/10 bg-gray-950">
    <div class="mx-auto flex max-w-7xl flex-col gap-4 px-4 py-6 text-sm text-gray-400 sm:px-6 md:flex-row md:items-center md:justify-between lg:px-8">
        <p>&copy; {{ now()->year }} {{ config('app.name') }}</p>

        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:gap-3">
            <a href="https://www.themoviedb.org" target="_blank" rel="noopener" class="shrink-0">
                <img src="{{ asset('images/tmdb-logo.svg') }}" alt="TMDB" class="h-3 w-auto">
            </a>
            <p>This product uses the TMDB API but is not endorsed or certified by TMDB.</p>
        </div>
    </div>
</footer>
