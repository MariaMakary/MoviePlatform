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
            <h1 class="text-2xl font-bold text-white">Components</h1>
            <p class="mt-1 text-sm text-gray-400">Shared Blade components with example data. Only available when <code class="text-neon-rose">APP_ENV=local</code>.</p>
        </header>

        <section class="space-y-4">
            <h2 class="text-lg font-semibold text-white"><code>&lt;x-logo /&gt;</code> and <code>&lt;x-nav-link href="…" :active="true" mobile&gt;</code></h2>
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
            <h2 class="text-lg font-semibold text-white"><code>&lt;x-icons.star class="h-4 w-4 text-neon-rose" /&gt;</code></h2>
            <x-icons.star class="h-6 w-6 text-neon-rose" />
        </section>

        <section class="space-y-4">
            <h2 class="text-lg font-semibold text-white"><code>&lt;x-movie-card :movie="$movie" href="…" /&gt;</code></h2>
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6">
                @foreach ($movies as $movie)
                    <x-movie-card :movie="$movie" href="#" />
                @endforeach
            </div>
        </section>

        <section class="space-y-4">
            <h2 class="text-lg font-semibold text-white"><code>&lt;x-rating-stars :value="3.5" size="sm|md|lg" /&gt;</code></h2>
            <div class="flex flex-wrap items-center gap-6">
                <x-rating-stars :value="1" size="sm" />
                <x-rating-stars :value="3.5" />
                <x-rating-stars :value="5" size="lg" />
                <x-rating-stars :value="0" />
            </div>
        </section>

        <section class="space-y-4">
            <h2 class="text-lg font-semibold text-white"><code>&lt;x-avatar name="…" :src="$url" size="xs|sm|md|lg|xl" /&gt;</code></h2>
            <div class="flex flex-wrap items-end gap-4">
                <x-avatar name="Maria Makary" size="xs" />
                <x-avatar name="Joy Alahel" size="sm" />
                <x-avatar name="Joe" />
                <x-avatar name="Tonio Jreige" size="lg" />
                <x-avatar name="Movie Fan" size="xl" />
            </div>
        </section>

        <section class="space-y-4">
            <h2 class="text-lg font-semibold text-white"><code>&lt;x-button variant="primary|secondary|ghost|danger" size="sm|md|lg" :href="…"&gt;</code></h2>
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
        </section>

        <section class="space-y-4">
            <h2 class="text-lg font-semibold text-white"><code>&lt;x-input label="…" name="…" type="text|email|password" wire:model="…" /&gt;</code></h2>
            <div class="grid max-w-xl gap-4 sm:grid-cols-2">
                <x-input label="Email" name="demo_email" type="email" placeholder="maria@example.com" />
                <x-input label="Password" name="demo_password" type="password" />
            </div>
            <p class="text-sm text-gray-400">A field with an error gets a pink border and its message below it (<code>@@error('name')</code>, filled from the service's 422).</p>
        </section>

        <section class="space-y-4">
            <h2 class="text-lg font-semibold text-white"><code>&lt;x-modal name="…" title="…"&gt;</code></h2>
            <p class="text-sm text-gray-400">Open with <code>$dispatch('open-modal', 'name')</code> in Alpine or <code>$this->dispatch('open-modal', name: 'name')</code> in Livewire.</p>
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
            <h2 class="text-lg font-semibold text-white"><code>&lt;x-empty-state title="…" description="…"&gt;</code></h2>
            <x-empty-state title="Your watchlist is empty" description="Add movies you want to watch and they will show up here.">
                <x-button href="#">Browse movies</x-button>
            </x-empty-state>
        </section>

        <section class="space-y-4">
            <h2 class="text-lg font-semibold text-white"><code>&lt;x-service-unavailable service="Catalog" :retry="true" /&gt;</code></h2>
            <x-service-unavailable service="Catalog" />
            <x-service-unavailable />
        </section>

        <section class="space-y-4">
            <h2 class="text-lg font-semibold text-white"><code>&lt;x-date :value="$utc" /&gt;</code> and <code>&lt;x-flash /&gt;</code></h2>
            <p class="text-sm text-gray-300">2026-11-03T18:20:00Z is shown as <x-date value="2026-11-03T18:20:00Z" class="font-semibold text-white" />.</p>
        </section>
    </div>
@endsection
