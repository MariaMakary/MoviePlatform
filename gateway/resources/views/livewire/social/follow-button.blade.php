<div class="inline-flex flex-col items-start gap-1">
    @if ($self)
        {{-- Nobody follows themselves --}}
    @elseif ($loggedIn && $this->isUnavailable('follow'))
        <x-service-unavailable :service="$unavailable['follow']" :retry="true" compact />
    @elseif ($loggedIn && $following === null)
        {{-- State not loaded yet --}}
    @else
        <x-button :variant="$following ? 'ghost' : 'primary'" :size="$compact ? 'sm' : 'md'"
                  wire:click="{{ $loggedIn ? 'toggle' : 'logIn' }}" wire:loading.attr="disabled" :aria-pressed="$following ? 'true' : 'false'">
            {{ $following ? 'Following' : 'Follow' }}
        </x-button>
    @endif

    @if ($error)
        <p role="alert" class="text-sm text-neon-rose">{{ $error }}</p>
    @endif
</div>
