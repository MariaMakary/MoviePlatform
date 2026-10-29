@php($links = [
    ['label' => 'Home', 'href' => route('home'), 'active' => request()->routeIs('home')],
    ['label' => 'Movies', 'href' => '#', 'active' => false],
    ['label' => 'Movie nights', 'href' => '#', 'active' => false],
])

<header x-data="{ open: false }" class="sticky top-0 z-40 border-b border-white/10 bg-jet/90 backdrop-blur">
    <nav class="mx-auto flex h-16 max-w-7xl items-center gap-4 px-4 sm:px-6 lg:px-8" aria-label="Main">
        <x-logo />

        <div class="hidden items-center gap-1 md:flex">
            @foreach ($links as $link)
                <x-nav-link :href="$link['href']" :active="$link['active']">{{ $link['label'] }}</x-nav-link>
            @endforeach
        </div>

        <div class="hidden flex-1 md:block">
            @include('layouts.partials.search')
        </div>

        <div class="ml-auto flex items-center gap-2 md:ml-0">
            @include('layouts.partials.notification-bell')

            <div class="hidden md:block">
                @include('layouts.partials.user-menu')
            </div>

            <button type="button" @click="open = ! open" class="rounded-md p-2 text-gray-300 hover:bg-white/5 hover:text-white md:hidden"
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

    <div id="mobile-menu" x-show="open" x-cloak @click.outside="open = false" class="space-y-3 border-t border-white/10 px-4 py-4 md:hidden">
        @include('layouts.partials.search')

        <div class="flex flex-col gap-1">
            @foreach ($links as $link)
                <x-nav-link :href="$link['href']" :active="$link['active']" mobile>{{ $link['label'] }}</x-nav-link>
            @endforeach
        </div>

        <div class="border-t border-white/10 pt-3">
            @include('layouts.partials.user-menu')
        </div>
    </div>
</header>
