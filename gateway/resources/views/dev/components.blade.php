@extends('layouts.app')

@php
    $movies = [
        ['id' => '0193a7c1-3333-7aaa-8bbb-000000000100', 'title' => 'Inception', 'poster_path' => '/oYuLEt3zVCKq57qu2F8dT7NIa6f.jpg', 'release_date' => '2010-07-16', 'vote_average' => 8.4],
        ['id' => '0193a7c1-3333-7aaa-8bbb-000000000101', 'title' => 'Interstellar', 'poster_path' => '/gEU2QniE6E77NI6lCU6MxlNBvIx.jpg', 'release_date' => '2014-11-05', 'vote_average' => 8.5],
        ['id' => '0193a7c1-3333-7aaa-8bbb-000000000102', 'title' => 'A locally added movie with a very long title and no poster', 'poster_path' => null, 'release_date' => '2026-03-01', 'vote_average' => null],
        ['id' => '0193a7c1-3333-7aaa-8bbb-000000000103', 'title' => 'Untitled project', 'poster_path' => null, 'release_date' => null, 'vote_average' => 6.0],
    ];
@endphp

@section('content')
    <div class="space-y-12">
        <header>
            <h1 class="text-2xl font-bold text-fg">Components</h1>
            <p class="mt-1 text-sm text-muted">Shared Blade components with example data. Only available when <code class="text-neon-rose">APP_ENV=local</code>.</p>
        </header>

        <section class="space-y-4">
            <h2 class="text-lg font-semibold text-fg">Colour tokens</h2>
            <p class="text-sm text-muted">Use these, never raw greys, <code>white/5</code> or new hex values. Pink is the one main button per screen; blue is links, focus and active states.</p>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                @foreach ([
                    ['jet', 'bg-jet', 'Page background'],
                    ['surface-1', 'bg-surface-1', 'Cards, inputs'],
                    ['surface-2', 'bg-surface-2', 'Menus, modals, hover'],
                    ['line', 'bg-line', 'Dividers, card borders'],
                    ['line-strong', 'bg-line-strong', 'Input borders (3:1)'],
                    ['fg', 'bg-fg', 'Main text'],
                    ['muted', 'bg-muted', 'Secondary text'],
                    ['subtle', 'bg-subtle', 'Meta and large text only'],
                    ['cerulean', 'bg-cerulean', 'Blue fills, with white text'],
                    ['cerulean-light', 'bg-cerulean-light', 'Links and small blue text'],
                    ['neon-rose', 'bg-neon-rose', 'The main button, with jet text'],
                ] as [$name, $swatch, $use])
                    <div class="overflow-hidden rounded-xl border border-line">
                        <div class="h-12 {{ $swatch }}"></div>
                        <div class="p-3 text-sm">
                            <p class="font-semibold text-fg"><code>{{ $name }}</code></p>
                            <p class="text-muted">{{ $use }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="flex flex-wrap items-baseline gap-x-6 gap-y-2">
                <h1 class="text-4xl font-bold text-fg">Display 39</h1>
                <h2 class="text-3xl font-semibold text-fg">Display 31</h2>
                <h3 class="text-2xl font-semibold text-fg">Display 25</h3>
                <p class="text-base text-fg">Body 16, Instrument Sans</p>
            </div>
        </section>

        <section class="space-y-4">
            <h2 class="text-lg font-semibold text-fg"><code>&lt;x-logo /&gt;</code> and <code>&lt;x-nav-link href="…" :active="true" mobile&gt;</code></h2>
            <div class="flex flex-wrap items-start gap-8">
                <x-logo />
                <div class="flex items-center gap-1">
                    <x-nav-link href="#" :active="true">Active</x-nav-link>
                    <x-nav-link href="#">Inactive</x-nav-link>
                </div>
                <div class="flex flex-col gap-1">
                    <x-nav-link href="#" :active="true" mobile>Mobile active</x-nav-link>
                    <x-nav-link href="#" mobile>Mobile inactive</x-nav-link>
                </div>
            </div>
        </section>

        <section class="space-y-4">
            <h2 class="text-lg font-semibold text-fg"><code>&lt;x-icons.star class="h-4 w-4 text-neon-rose" /&gt;</code>, <code>&lt;x-icons.bookmark :filled="true" /&gt;</code>, <code>&lt;x-icons.plus /&gt;</code> and <code>&lt;x-icons.weather condition="clear|clouds|rain|storm|snow" /&gt;</code></h2>
            <div class="flex items-center gap-3">
                @foreach (['clear', 'clouds', 'rain', 'storm', 'snow'] as $condition)
                    <x-icons.weather :condition="$condition" class="h-6 w-6 text-muted" title="{{ $condition }}" />
                @endforeach
                <x-icons.star class="h-6 w-6 text-neon-rose" />
                <x-icons.bookmark class="h-6 w-6 text-muted" />
                <x-icons.bookmark class="h-6 w-6 text-neon-rose" :filled="true" />
                <x-icons.plus class="h-6 w-6 text-muted" />
            </div>
        </section>

        <section class="space-y-4">
            <h2 class="text-lg font-semibold text-fg"><code>&lt;x-movie-card :movie="$movie" href="…" :show-release-date="true" /&gt;</code></h2>
            <p class="text-sm text-muted">Takes a Catalog movie card (<code>poster_url</code>, <code>year</code>, <code>release_date</code>) or raw TMDB fields (<code>poster_path</code>). The last card shows the release date instead of the year.</p>
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6">
                @foreach ($movies as $movie)
                    <x-movie-card :movie="$movie" href="#" />
                @endforeach
                <x-movie-card href="#" :show-release-date="true" :movie="[
                    'id' => '0193a7c1-3333-7aaa-8bbb-000000000104',
                    'title' => 'A Catalog card',
                    'poster_url' => 'https://image.tmdb.org/t/p/w342/gEU2QniE6E77NI6lCU6MxlNBvIx.jpg',
                    'year' => 2026,
                    'release_date' => '2026-12-18',
                    'vote_average' => null,
                ]" />
            </div>
        </section>

        <section class="space-y-4">
            <h2 class="text-lg font-semibold text-fg"><code>&lt;x-movie-row label="…" :more="'loadMore()'"&gt;</code> with <code>&lt;x-movie-row.item&gt;</code></h2>
            <p class="text-sm text-muted">One sideways-scrolling row of movie cards: trackpad, swipe, and arrow buttons on desktop. <code>more</code> is a Livewire call (for example <code>"loadMore('popular')"</code>) run when the end of the row comes into view, with a loading card at the end; leave it <code>null</code> after the last page. <code>&lt;x-movie-row.item width="w-28"&gt;</code> changes the item width (default: a movie card). Used by the home page rows and the movie page's cast.</p>
            <x-movie-row label="Example row">
                @foreach (array_merge($movies, $movies) as $i => $movie)
                    <x-movie-row.item wire:key="row-demo-{{ $i }}">
                        <x-movie-card :movie="$movie" href="#" />
                    </x-movie-row.item>
                @endforeach
            </x-movie-row>
        </section>

        <section class="space-y-4">
            <h2 class="text-lg font-semibold text-fg"><code>&lt;x-rating-stars :value="3.5" size="sm|md|lg" /&gt;</code></h2>
            <div class="flex flex-wrap items-center gap-6">
                <x-rating-stars :value="1" size="sm" />
                <x-rating-stars :value="3.5" />
                <x-rating-stars :value="5" size="lg" />
                <x-rating-stars :value="0" />
            </div>
        </section>

        <section class="space-y-4">
            <h2 class="text-lg font-semibold text-fg"><code>&lt;x-avatar name="…" :src="$url" size="xs|sm|md|lg|xl" /&gt;</code></h2>
            <div class="flex flex-wrap items-end gap-4">
                <x-avatar name="Maria Makary" size="xs" />
                <x-avatar name="Joy Alahel" size="sm" />
                <x-avatar name="Joe" />
                <x-avatar name="Tonio Jreige" size="lg" />
                <x-avatar name="Movie Fan" size="xl" />
            </div>
        </section>

        <section class="space-y-4">
            <h2 class="text-lg font-semibold text-fg"><code>&lt;x-button variant="primary|secondary|ghost|danger" size="sm|md|lg" :href="…"&gt;</code></h2>
            <div class="flex flex-wrap items-center gap-3">
                <x-button>Primary</x-button>
                <x-button variant="secondary">Secondary</x-button>
                <x-button variant="ghost">Ghost</x-button>
                <x-button variant="danger">Delete</x-button>
                <x-button size="sm">Small</x-button>
                <x-button size="lg">Large</x-button>
                <x-button href="#" variant="secondary">Link button</x-button>
                <x-button disabled>Disabled</x-button>
            </div>
            <p class="text-sm text-muted">A button with <code>wire:click</code> (or a <code>wire:target</code>) shows a spinner and is disabled while that action runs. A submit button needs <code>wire:target="&lt;the form's method&gt;"</code>.</p>
        </section>

        <section class="space-y-4">
            <h2 class="text-lg font-semibold text-fg"><code>&lt;x-input label="…" name="…" type="text|email|password" wire:model="…" /&gt;</code></h2>
            <div class="grid max-w-xl gap-4 sm:grid-cols-2">
                <x-input label="Email" name="demo_email" type="email" placeholder="maria@example.com" />
                <x-input label="Password" name="demo_password" type="password" />
            </div>
            <p class="text-sm text-muted">A field with an error gets a pink border and its message below it (<code>@@error('name')</code>, filled from the service's 422).</p>
        </section>

        <section class="space-y-4">
            <h2 class="text-lg font-semibold text-fg"><code>&lt;x-modal name="…" title="…"&gt;</code></h2>
            <p class="text-sm text-muted">Open with <code>$dispatch('open-modal', 'name')</code> in Alpine or <code>$this->dispatch('open-modal', name: 'name')</code> in Livewire.</p>
            <x-button variant="secondary" x-data x-on:click="$dispatch('open-modal', 'demo')">Open modal</x-button>

            <x-modal name="demo" title="Delete this review?">
                This removes your review of Inception. You can write a new one later.

                <x-slot:footer>
                    <x-button variant="ghost" x-on:click="$dispatch('close-modal', 'demo')">Cancel</x-button>
                    <x-button variant="danger" x-on:click="$dispatch('close-modal', 'demo')">Delete</x-button>
                </x-slot:footer>
            </x-modal>
        </section>

        <section class="space-y-4">
            <h2 class="text-lg font-semibold text-fg"><code>&lt;x-empty-state title="…" description="…"&gt;</code></h2>
            <x-empty-state title="Your watchlist is empty" description="Add movies you want to watch and they will show up here.">
                <x-button href="#">Browse movies</x-button>
            </x-empty-state>
        </section>

        <section class="space-y-4">
            <h2 class="text-lg font-semibold text-fg"><code>&lt;x-service-unavailable service="Catalog" :retry="true" compact /&gt;</code></h2>
            <x-service-unavailable service="Catalog" />
            <x-service-unavailable />
            <p class="text-sm text-muted"><code>compact</code>: one line, for a small part of the page such as a button.</p>
            <x-service-unavailable service="Engagement" compact />
        </section>

        <section class="space-y-4">
            <h2 class="text-lg font-semibold text-fg"><code>&lt;x-search-spinner target="q" /&gt;</code></h2>
            <p class="text-sm text-muted">Inside a <code>relative</code> wrapper, after a live search input with <code>pr-10</code>: spins at the right edge while <code>target</code> loads.</p>
            <div class="relative max-w-sm">
                <input type="search" placeholder="Search…" class="block w-full rounded-lg border border-line-strong bg-surface-1 py-2 pr-10 pl-3 text-sm text-fg placeholder-subtle">
                <svg class="pointer-events-none absolute top-1/2 right-3 h-4 w-4 -translate-y-1/2 animate-spin text-muted" fill="none" viewBox="0 0 24 24" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4Z"/></svg>
            </div>
        </section>

        <section class="space-y-4">
            <h2 class="text-lg font-semibold text-fg"><code>&lt;x-error-page code="404" title="…" message="…"&gt;</code></h2>
            <p class="text-sm text-muted">Used by <code>resources/views/errors/*</code> (403, 404, 419, 429, 500, 503). The slot adds buttons next to "Back to home".</p>
            <div class="rounded-2xl border border-line">
                <x-error-page code="404" title="Page not found" message="This page does not exist, or it was moved or deleted.">
                    <x-button href="#" variant="ghost">Search movies</x-button>
                </x-error-page>
            </div>
        </section>

        <section class="space-y-4">
            <h2 class="text-lg font-semibold text-fg"><code>&lt;x-reserved id="…" title="…" subtitle="…" compact /&gt;</code></h2>
            <p class="text-sm text-muted">Space kept for a feature another task adds: replace the tag with that feature's component. <code>compact</code> is the small version for a button.</p>
            <x-reserved id="demo-reserved" title="Similar movies" subtitle="If you liked this one." />
            <x-reserved compact id="demo-reserved-compact" title="Watchlist" />
        </section>

        <section class="space-y-4">
            <h2 class="text-lg font-semibold text-fg"><code>&lt;x-date :value="$utc" /&gt;</code> and <code>&lt;x-flash /&gt;</code></h2>
            <p class="text-sm text-muted">2026-11-03T18:20:00Z is shown as <x-date value="2026-11-03T18:20:00Z" class="font-semibold text-fg" />.</p>
        </section>
    </div>
@endsection
