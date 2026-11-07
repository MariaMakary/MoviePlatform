<div @class(['space-y-6', 'mx-auto max-w-2xl' => ! $embedded, 'rounded-2xl border border-white/10 bg-white/[0.03] p-6' => $embedded])>
    @if ($embedded)
        <div class="space-y-1">
            <h2 class="text-lg font-semibold text-white">Favorite genres</h2>
            <p class="text-sm text-gray-400">
                @if ($savedNames->isNotEmpty())
                    {{ $savedNames->join(', ') }}
                @else
                    None picked yet.
                @endif
            </p>
        </div>
    @else
        <div class="space-y-2">
            <h1 class="text-2xl font-bold tracking-tight text-white">What do you like to watch?</h1>
            <p class="text-gray-400">Pick your favorite genres to get better recommendations. You can change them later on your profile.</p>
        </div>
    @endif

    @if ($this->isUnavailable('save'))
        <p role="alert" class="rounded-lg border border-neon-rose/40 bg-neon-rose/10 px-3 py-2 text-sm text-pink-100">
            {{ $unavailable['save'] }} isn't responding, so nothing was saved. Try again in a moment.
        </p>
    @endif

    @if ($this->isUnavailable('genres'))
        <x-service-unavailable :service="$unavailable['genres']" :retry="true" />
    @else
        <form wire:submit="save" class="space-y-4">
            <fieldset>
                <legend class="sr-only">Genres</legend>
                <div class="flex flex-wrap gap-2">
                    @foreach ($genres as $genre)
                        <label wire:key="genre-{{ $genre['id'] }}" class="cursor-pointer">
                            <input type="checkbox" wire:model="selected" value="{{ $genre['id'] }}" class="peer sr-only">
                            <span class="inline-flex items-center rounded-full border border-white/15 bg-white/5 px-4 py-2 text-sm font-medium text-gray-200 transition select-none hover:bg-white/10 peer-checked:border-neon-rose peer-checked:bg-neon-rose peer-checked:text-jet peer-focus-visible:ring-2 peer-focus-visible:ring-cerulean">
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
                    <a href="{{ route('home') }}" class="text-sm text-gray-400 hover:text-white hover:underline">Skip for now</a>
                @endunless
                <x-button type="submit" wire:loading.attr="disabled" wire:target="save">Save</x-button>
            </div>
        </form>
    @endif

    {{-- Catalog down: "Skip for now" must still work --}}
    @if (! $embedded && $this->isUnavailable('genres'))
        <a href="{{ route('home') }}" class="inline-block text-sm text-gray-400 hover:text-white hover:underline">Skip for now</a>
    @endif
</div>
