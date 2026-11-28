@extends('layouts.app')

@section('content')
    <div class="space-y-6">
        <header>
            <h1 class="text-2xl font-bold text-white">API docs</h1>
            <p class="mt-1 text-sm text-gray-400">
                Swagger UI for every service, served from its own port, so "Try it out" works without CORS. Use
                <span class="text-white">Authorize</span> with a JWT for user routes. The contract stays in
                <code>docs/api/&lt;service&gt;.md</code>. Only available when <code class="text-neon-rose">APP_ENV=local</code>.
            </p>
        </header>

        <ul class="grid gap-4 md:grid-cols-2">
            @foreach ($services as $service)
                <li>
                    <a href="{{ config("services.{$service}.url") }}/docs/" target="_blank" rel="noopener"
                       class="flex items-center justify-between gap-2 rounded-2xl border border-white/10 p-4 hover:bg-white/5">
                        <span class="font-semibold text-white">{{ Str::headline($service) }}</span>
                        <code class="text-xs text-gray-500">{{ config("services.{$service}.url") }}/docs/</code>
                    </a>
                </li>
            @endforeach
        </ul>
    </div>
@endsection
