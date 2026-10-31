@inject('authSession', 'App\Services\AuthSession')
@php($user = $authSession->user())

@if ($user)
    <div class="flex items-center gap-3">
        <x-avatar :name="$user['name']" :src="$user['avatar']" size="sm" />
        <span class="max-w-40 truncate text-sm font-medium text-white">{{ $user['name'] }}</span>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <x-button type="submit" variant="ghost" size="sm">Log out</x-button>
        </form>
    </div>
@else
    <div class="flex items-center gap-2">
        <x-button :href="route('login')" variant="ghost" size="sm">Log in</x-button>
        <x-button :href="route('register')" size="sm">Sign up</x-button>
    </div>
@endif
