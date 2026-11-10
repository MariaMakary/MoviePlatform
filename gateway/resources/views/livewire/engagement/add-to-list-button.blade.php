<div class="relative inline-flex flex-col items-start gap-1" x-data="{ open: false }" x-on:click.outside="open = false" x-on:keydown.escape.window="open = false">
    @if (! $loggedIn)
        <button type="button" wire:click="logIn"
                class="inline-flex items-center gap-2 rounded-lg bg-white/5 px-3 py-2 text-sm font-semibold text-gray-100 ring-1 ring-white/10 transition hover:bg-white/10 focus-visible:ring-2 focus-visible:ring-cerulean focus-visible:outline-none">
            <x-icons.plus class="h-4 w-4" />
            Log in to add to a list
        </button>
    @elseif ($this->isUnavailable('lists'))
        <x-service-unavailable :service="$unavailable['lists']" :retry="true" compact />
    @else
        <button type="button" x-on:click="open = ! open" :aria-expanded="open.toString()" aria-haspopup="true" aria-controls="add-to-list-menu"
                class="inline-flex items-center gap-2 rounded-lg bg-white/5 px-3 py-2 text-sm font-semibold text-gray-100 ring-1 ring-white/10 transition hover:bg-white/10 focus-visible:ring-2 focus-visible:ring-cerulean focus-visible:outline-none">
            <x-icons.plus class="h-4 w-4" />
            Add to list
        </button>

        <div id="add-to-list-menu" x-show="open" x-cloak x-transition.opacity
             class="absolute top-full left-0 z-30 mt-2 w-72 overflow-hidden rounded-xl border border-white/10 bg-jet shadow-2xl">
            @if ($lists === [])
                <p class="px-4 py-3 text-sm text-gray-400">You have no lists yet.</p>
            @else
                <ul class="max-h-72 overflow-y-auto py-1">
                    @foreach ($lists as $list)
                        <li wire:key="add-to-list-{{ $list['id'] }}">
                            <button type="button" role="menuitemcheckbox" aria-checked="{{ $list['contains_movie'] ? 'true' : 'false' }}"
                                    wire:click="toggle('{{ $list['id'] }}', {{ $list['contains_movie'] ? 'true' : 'false' }})"
                                    wire:loading.attr="disabled" wire:target="toggle"
                                    class="flex w-full items-center gap-3 px-4 py-2 text-left text-sm text-gray-200 transition hover:bg-white/5 disabled:opacity-50">
                                <span @class(['flex h-5 w-5 shrink-0 items-center justify-center rounded border',
                                              'border-cerulean bg-cerulean text-white' => $list['contains_movie'],
                                              'border-white/20' => ! $list['contains_movie']]) aria-hidden="true">
                                    @if ($list['contains_movie'])
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                                    @endif
                                </span>
                                <span class="min-w-0 flex-1 truncate">{{ $list['title'] }}</span>
                                @unless ($list['is_public'])
                                    <span class="text-xs text-gray-500">Private</span>
                                @endunless
                            </button>
                        </li>
                    @endforeach
                </ul>
            @endif
            <a href="{{ route('lists.create') }}" class="flex items-center gap-2 border-t border-white/10 px-4 py-3 text-sm font-semibold text-cerulean hover:bg-white/5">
                <x-icons.plus class="h-4 w-4" />
                New list
            </a>
        </div>
    @endif

    @if ($error)
        <p role="alert" class="text-xs text-neon-rose">{{ $error }}</p>
    @endif
</div>
