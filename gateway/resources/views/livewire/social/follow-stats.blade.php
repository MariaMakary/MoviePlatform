<div>
    @if ($this->isUnavailable('stats'))
        <x-service-unavailable :service="$unavailable['stats']" :retry="true" compact />
    @elseif ($stats)
        <p class="flex gap-4 text-sm text-gray-300">
            <a href="{{ route('users.followers', $userId) }}" class="hover:text-white hover:underline">
                <span class="font-semibold text-white">{{ number_format($stats['followers']) }}</span> {{ Str::plural('follower', $stats['followers']) }}
            </a>
            <a href="{{ route('users.following', $userId) }}" class="hover:text-white hover:underline">
                <span class="font-semibold text-white">{{ number_format($stats['following']) }}</span> following
            </a>
        </p>
    @endif
</div>
