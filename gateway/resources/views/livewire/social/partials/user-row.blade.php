{{-- One person in a list: avatar, name, bio and a follow button. $user: an Identity public user.
     $following: user id => is_following from one bulk call ([] for guests, null when Social is down: no buttons). --}}
<li class="flex items-center gap-4 py-4">
    <x-avatar :name="$user['name']" :src="$user['avatar']" />
    <div class="min-w-0 flex-1">
        <a href="{{ route('users.show', $user['id']) }}" class="block truncate font-semibold text-white hover:underline">{{ $user['name'] }}</a>
        @if ($user['bio'])
            <p class="truncate text-sm text-gray-400">{{ $user['bio'] }}</p>
        @endif
    </div>
    @if ($following !== null)
        <livewire:social.follow-button :user-id="$user['id']" :following="$following[$user['id']] ?? null" compact :key="'follow-'.$user['id']" />
    @endif
</li>
