<div class="space-y-6">
    <header class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-white">Services</h1>
            <p class="mt-1 text-sm text-gray-400">
                Calls <code>GET /api/health</code> on every service through its client. A stopped service shows
                <code class="text-neon-rose">&lt;x-service-unavailable /&gt;</code> and the rest of the page keeps working.
                Only available when <code class="text-neon-rose">APP_ENV=local</code>.
            </p>
        </div>
        <x-button variant="secondary" size="sm" wire:click="$refresh" wire:loading.attr="disabled">Check again</x-button>
    </header>

    <div class="grid gap-4 md:grid-cols-2">
        @foreach ($health as $service => $status)
            <section wire:key="service-{{ $service }}" class="space-y-3 rounded-2xl border border-white/10 p-4">
                <div class="flex items-center justify-between gap-2">
                    <h2 class="font-semibold text-white">{{ Str::headline($service) }}</h2>
                    <code class="text-xs text-gray-500">{{ config("services.{$service}.url") }}</code>
                </div>

                @if ($this->isUnavailable($service))
                    <x-service-unavailable :service="$unavailable[$service]" :retry="true" />
                @else
                    <dl class="flex flex-wrap gap-2 text-xs">
                        @foreach ($status ?? [] as $key => $value)
                            <div class="rounded-md bg-cerulean/15 px-2 py-1 text-sky-100">
                                <dt class="inline text-gray-400">{{ $key }}</dt>
                                <dd class="inline font-semibold">{{ is_scalar($value) ? $value : json_encode($value) }}</dd>
                            </div>
                        @endforeach
                    </dl>
                @endif
            </section>
        @endforeach
    </div>
</div>
