@php($loggedIn = app(\App\Services\AuthSession::class)->check())
@php($links = array_values(array_filter([
    ['label' => 'Home', 'href' => route('home'), 'active' => request()->routeIs('home')],
    $loggedIn ? ['label' => 'Feed', 'href' => route('feed'), 'active' => request()->routeIs('feed')] : null,
    ['label' => 'Movies', 'href' => '#', 'active' => false],
    $loggedIn ? ['label' => 'Watchlist', 'href' => route('watchlist'), 'active' => request()->routeIs('watchlist')] : null,
    $loggedIn ? ['label' => 'History', 'href' => route('history'), 'active' => request()->routeIs('history')] : null,
    ['label' => 'Lists', 'href' => $loggedIn ? route('lists.index') : route('lists.browse'), 'active' => request()->routeIs('lists.*')],
    $loggedIn ? ['label' => 'People', 'href' => route('people'), 'active' => request()->routeIs('people', 'users.*')] : null,
    ['label' => 'Movie nights', 'href' => route('movie-nights.index'), 'active' => request()->routeIs('movie-nights.*')],
    $loggedIn ? ['label' => 'Discussions', 'href' => route('rooms.index'), 'active' => request()->routeIs('rooms.*', 'movies.discussion')] : null,
    ['label' => 'Cinemas', 'href' => route('cinemas'), 'active' => request()->routeIs('cinemas')],
    app(\App\Services\AuthSession::class)->isAdmin() ? ['label' => 'Admin', 'href' => route('admin.dashboard'), 'active' => request()->routeIs('admin.*')] : null,
])))

<header x-data="{ open: false }" class="sticky top-0 z-40 border-b border-line bg-jet/90 backdrop-blur">
    <nav class="mx-auto flex h-16 max-w-7xl items-center gap-3 px-4 sm:px-6 lg:px-8" aria-label="Main">
        <x-logo />

        <div class="hidden items-center gap-0.5 xl:flex">
            @foreach ($links as $link)
                <x-nav-link :href="$link['href']" :active="$link['active']">{{ $link['label'] }}</x-nav-link>
            @endforeach
        </div>

        <div class="hidden min-w-0 flex-1 2xl:block">
            @include('layouts.partials.search')
        </div>

        <div class="ml-auto flex shrink-0 items-center gap-2 2xl:ml-0">
            <a href="{{ route('search') }}" class="hidden rounded-md p-2 text-muted hover:bg-surface-2 hover:text-fg xl:inline-flex 2xl:hidden" aria-label="Search movies">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35M17 10.5a6.5 6.5 0 1 1-13 0 6.5 6.5 0 0 1 13 0Z"/>
                </svg>
            </a>

            @include('layouts.partials.notification-bell')

            <div class="hidden xl:block">
                @include('layouts.partials.user-menu', ['compact' => true])
            </div>

            <button type="button" @click="open = ! open" class="rounded-md p-2 text-muted hover:bg-surface-2 hover:text-fg xl:hidden"
                    :aria-expanded="open.toString()" aria-controls="mobile-menu" aria-label="Open menu">
                <svg x-show="! open" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/>
                </svg>
                <svg x-show="open" x-cloak class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
    </nav>

    <div id="mobile-menu" x-show="open" x-cloak @click.outside="open = false" class="max-h-[calc(100dvh-4rem)] space-y-3 overflow-y-auto border-t border-line px-4 py-4 sm:px-6 xl:hidden">
        @include('layouts.partials.search')

        <div class="grid gap-1 sm:grid-cols-2 md:grid-cols-3">
            @foreach ($links as $link)
                <x-nav-link :href="$link['href']" :active="$link['active']" mobile>{{ $link['label'] }}</x-nav-link>
            @endforeach
        </div>

        <div class="border-t border-line pt-3">
            @include('layouts.partials.user-menu')
        </div>
    </div>
</header>
