<div @class(['space-y-6', 'mx-auto max-w-2xl' => ! $embedded, 'rounded-2xl border border-line bg-surface-1 p-6' => $embedded])>
    @if ($embedded)
        <div class="space-y-1">
            <h2 class="text-lg font-semibold text-fg">Favorite genres</h2>
            <p class="text-sm text-muted">
                @if ($this->isUnavailable('identity'))
                    Your saved genres can't be loaded right now.
                @elseif ($savedNames->isNotEmpty())
                    {{ $savedNames->join(', ') }}
                @else
                    None picked yet.
                @endif
            </p>
        </div>
    @else
        <div class="space-y-2">
            <h1 class="text-2xl font-bold tracking-tight text-fg">What do you like to watch?</h1>
            <p class="text-muted">Pick your favorite genres to get better recommendations. You can change them later on your profile.</p>
        </div>
    @endif

    @if ($this->isUnavailable('save'))
        <p role="alert" class="rounded-lg border border-neon-rose/40 bg-neon-rose/10 px-3 py-2 text-sm text-pink-100">
            {{ $unavailable['save'] }} isn't responding, so nothing was saved. Try again in a moment.
        </p>
    @endif

    {{-- Identity down: the saved genres are unknown, so no form (saving would replace them with this new pick) --}}
    @if ($this->isUnavailable('identity'))
        <x-service-unavailable :service="$unavailable['identity']" :retry="true" />
    @elseif ($this->isUnavailable('genres'))
        <x-service-unavailable :service="$unavailable['genres']" :retry="true" />
    @elseif ($genres === [])
        <x-empty-state title="No genres yet" description="Genres show up here once Catalog has synced them from TMDB." />
    @else
        <form wire:submit="save" class="space-y-4">
            <fieldset>
                <legend class="sr-only">Genres</legend>
                <div class="flex flex-wrap gap-2">
                    @foreach ($genres as $genre)
                        <label wire:key="genre-{{ $genre['id'] }}" class="cursor-pointer">
                            <input type="checkbox" wire:model="selected" value="{{ $genre['id'] }}" class="peer sr-only">
                            <span class="inline-flex items-center rounded-full border border-line-strong bg-surface-1 px-4 py-2 text-sm font-medium text-fg transition select-none hover:bg-surface-2 peer-checked:border-cerulean peer-checked:bg-cerulean peer-checked:text-white peer-focus-visible:ring-2 peer-focus-visible:ring-cerulean">
                                {{ $genre['name'] }}
                            </span>
                        </label>
                    @endforeach
                </div>
            </fieldset>

            @error('selected') <p role="alert" class="text-sm text-neon-rose">{{ $message }}</p> @enderror
            @error('selected.*') <p role="alert" class="text-sm text-neon-rose">{{ $message }}</p> @enderror
            @error('genre_ids') <p role="alert" class="text-sm text-neon-rose">{{ $message }}</p> @enderror
            @error('genre_ids.*') <p role="alert" class="text-sm text-neon-rose">{{ $message }}</p> @enderror

            <div @class(['flex items-center gap-4', 'justify-end' => $embedded, 'justify-between' => ! $embedded])>
                @unless ($embedded)
                    <a href="{{ route('home') }}" class="text-sm text-muted hover:text-fg hover:underline">Skip for now</a>
                @endunless
                <x-button type="submit" :variant="$embedded ? 'secondary' : 'primary'" wire:loading.attr="disabled" wire:target="save">Save</x-button>
            </div>
        </form>
    @endif

    {{-- Identity or Catalog down: "Skip for now" must still work --}}
    @if (! $embedded && ($this->isUnavailable('identity') || $this->isUnavailable('genres')))
        <a href="{{ route('home') }}" class="inline-block text-sm text-muted hover:text-fg hover:underline">Skip for now</a>
    @endif
</div>
