{{-- One person in a list: avatar, name, bio and a follow button. $user: an Identity public user. --}}
<li class="flex items-center gap-4 py-4">
    <x-avatar :name="$user['name']" :src="$user['avatar']" />
    <div class="min-w-0 flex-1">
        <p class="truncate font-semibold text-white">{{ $user['name'] }}</p>
        @if ($user['bio'])
            <p class="truncate text-sm text-gray-400">{{ $user['bio'] }}</p>
        @endif
    </div>
    <livewire:social.follow-button :user-id="$user['id']" compact :key="'follow-'.$user['id']" />
</li>
