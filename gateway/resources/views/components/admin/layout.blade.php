@props(['title'])

{{-- A new admin page: wrap its view in this component and add its link to $links. --}}
@php($links = [
    ['label' => 'Dashboard', 'href' => route('admin.dashboard'), 'active' => request()->routeIs('admin.dashboard')],
])

<div class="flex flex-col gap-6 md:flex-row md:gap-8">
    <aside class="md:w-56 md:shrink-0">
        <p class="mb-2 px-3 text-xs font-semibold tracking-wider text-gray-500 uppercase">Admin</p>
        <nav class="flex gap-1 overflow-x-auto md:flex-col" aria-label="Admin">
            @foreach ($links as $link)
                <x-nav-link :href="$link['href']" :active="$link['active']">{{ $link['label'] }}</x-nav-link>
            @endforeach
        </nav>
    </aside>

    <div class="min-w-0 flex-1 space-y-6">
        <h1 class="text-2xl font-bold text-white">{{ $title }}</h1>

        {{ $slot }}
    </div>
</div>
