<div class="space-y-12">
    <section class="relative overflow-hidden rounded-2xl border border-white/10 bg-jet px-6 py-12 sm:px-10 sm:py-16">
        <div class="pointer-events-none absolute -top-24 -left-24 h-72 w-72 rounded-full bg-cerulean/30 blur-3xl" aria-hidden="true"></div>
        <div class="pointer-events-none absolute -right-24 -bottom-24 h-72 w-72 rounded-full bg-neon-rose/20 blur-3xl" aria-hidden="true"></div>

        <h1 class="relative max-w-2xl text-3xl font-bold tracking-tight text-white sm:text-5xl">
            <span class="text-neon-rose">Discover movies</span>, rate them and plan movie nights with friends.
        </h1>
        <p class="relative mt-4 max-w-xl text-base text-gray-400 sm:text-lg">
            Browse the catalog, keep a watchlist, follow friends and get recommendations based on what you love.
        </p>
    </section>

    @include('livewire.home.reserved-row', [
        'id' => 'recommendations-row',
        'title' => 'Recommended for you',
        'subtitle' => 'Picks based on what you rate and watch.',
    ])

    @include('livewire.home.movie-section', [
        'section' => 'popular',
        'title' => 'Popular',
        'subtitle' => 'What everyone is watching right now.',
        'showReleaseDate' => false,
    ])

    @include('livewire.home.reserved-row', [
        'id' => 'weather-row',
        'title' => 'For today\'s weather',
        'subtitle' => 'Movies that fit the weather outside.',
    ])

    @include('livewire.home.movie-section', [
        'section' => 'upcoming',
        'title' => 'Upcoming',
        'subtitle' => 'Coming soon to theaters.',
        'showReleaseDate' => true,
    ])
</div>
