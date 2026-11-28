@php
    use App\Livewire\Admin\Users;

    $confirmations = [
        'block' => ['Block this user?', "$pendingName can no longer log in, and their reviews, lists and activity are hidden. You can unblock them later.", 'Block', 'danger'],
        'unblock' => ['Unblock this user?', "$pendingName can log in again, and their reviews, lists and activity show again.", 'Unblock', 'primary'],
        'delete' => ['Delete this user?', "This deletes $pendingName's account and all their data in every part of KAIA. It cannot be undone.", 'Delete', 'danger'],
    ];
    $confirmation = $confirmations[$pendingAction ?? ''] ?? null;
@endphp

<x-admin.layout title="Users">
    <div class="max-w-md">
        <label for="users-search" class="sr-only">Search users by name or email</label>
        <div class="relative">
            <input id="users-search" type="search" wire:model.live.debounce.300ms="q" placeholder="Search by name or email…" autocomplete="off" maxlength="{{ Users::MAX_SEARCH }}"
                   class="block w-full rounded-lg border border-line-strong bg-surface-1 py-2 pl-3 pr-10 text-sm text-fg placeholder-subtle focus:ring-[1.5px] focus:ring-neon-rose/75 focus:outline-none">
            <x-search-spinner target="q" />
        </div>
    </div>

    @if ($notice)
        <p role="status" class="rounded-lg bg-cerulean/10 px-4 py-2 text-sm text-cerulean-light ring-1 ring-cerulean/30">{{ $notice }}</p>
    @endif
    @if ($error)
        <p role="alert" class="rounded-lg bg-neon-rose/10 px-4 py-2 text-sm text-pink-200 ring-1 ring-neon-rose/30">{{ $error }}</p>
    @endif

    <div wire:loading.class="opacity-50" class="space-y-4 transition">
        @if ($this->isUnavailable('users'))
            <x-service-unavailable :service="$unavailable['users']" :retry="true" />
        @elseif ($users === [])
            <x-empty-state :title="$searching ? 'No users found' : 'No users yet'"
                           :description="$searching ? 'Try another name or email.' : 'Accounts will show up here.'" />
        @else
            <p class="text-sm text-muted">{{ number_format($total) }} {{ Str::plural('user', $total) }}</p>

            <div class="relative overflow-x-auto rounded-2xl border border-line">
                <table class="w-full min-w-[48rem] text-left text-sm">
                    <thead class="border-b border-line bg-surface-1 text-xs tracking-wider text-muted uppercase">
                        <tr>
                            <th scope="col" class="px-4 py-3 font-semibold">User</th>
                            <th scope="col" class="px-4 py-3 font-semibold">Email</th>
                            <th scope="col" class="px-4 py-3 font-semibold">Role</th>
                            <th scope="col" class="px-4 py-3 font-semibold">Status</th>
                            <th scope="col" class="px-4 py-3 font-semibold">Joined</th>
                            <th scope="col" class="px-4 py-3 text-right font-semibold"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($users as $user)
                            @php($isMe = $user['id'] === $ownId)
                            <tr wire:key="admin-user-{{ $user['id'] }}">
                                <td class="px-4 py-3">
                                    <a href="{{ route('users.show', $user['id']) }}" class="flex items-center gap-3 text-fg hover:text-fg hover:underline" title="Open the public profile">
                                        <x-avatar :name="$user['name']" :src="$user['avatar']" size="sm" />
                                        <span class="truncate font-medium">{{ $user['name'] }}@if ($isMe) <span class="font-normal text-muted">(you)</span>@endif</span>
                                    </a>
                                </td>
                                <td class="px-4 py-3 text-muted">{{ $user['email'] }}</td>
                                <td class="px-4 py-3">
                                    @if ($user['role'] === 'admin')
                                        <span class="rounded-full bg-cerulean/15 px-2 py-0.5 text-xs font-medium text-cerulean-light ring-1 ring-cerulean/30">Admin</span>
                                    @else
                                        <span class="text-xs text-muted">User</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    @if ($user['is_blocked'])
                                        <span class="rounded-full bg-neon-rose/15 px-2 py-0.5 text-xs font-medium text-pink-200 ring-1 ring-neon-rose/30">Blocked</span>
                                    @else
                                        <span class="text-xs text-muted">Active</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-muted">
                                    <x-date :value="$user['created_at']" format="j M Y" />
                                </td>
                                <td class="px-4 py-3">
                                    @unless ($isMe)
                                        <div class="flex justify-end gap-2">
                                            @if ($user['is_blocked'])
                                                <x-button variant="ghost" size="sm" wire:click="confirm('unblock', '{{ $user['id'] }}', {{ Js::from($user['name']) }})">Unblock</x-button>
                                            @else
                                                <x-button variant="ghost" size="sm" wire:click="confirm('block', '{{ $user['id'] }}', {{ Js::from($user['name']) }})">Block</x-button>
                                            @endif
                                            <x-button variant="danger" size="sm" wire:click="confirm('delete', '{{ $user['id'] }}', {{ Js::from($user['name']) }})">Delete</x-button>
                                        </div>
                                    @endunless
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($lastPage > 1)
                <nav class="flex items-center justify-between text-sm text-muted" aria-label="Pagination">
                    <x-button variant="ghost" size="sm" wire:click="goToPage({{ $page - 1 }})" :disabled="$page <= 1">Previous</x-button>
                    <span>Page {{ $page }} of {{ $lastPage }}</span>
                    <x-button variant="ghost" size="sm" wire:click="goToPage({{ $page + 1 }})" :disabled="$page >= $lastPage">Next</x-button>
                </nav>
            @endif
        @endif
    </div>

    <x-modal name="manage-user" :title="$confirmation[0] ?? 'Confirm'">
        {{ $confirmation[1] ?? '' }}

        <x-slot:footer>
            <x-button variant="ghost" x-on:click="$dispatch('close-modal', 'manage-user')">Cancel</x-button>
            <x-button :variant="$confirmation[3] ?? 'primary'" wire:click="apply" wire:loading.attr="disabled" wire:target="apply">{{ $confirmation[2] ?? 'Confirm' }}</x-button>
        </x-slot:footer>
    </x-modal>
</x-admin.layout>
