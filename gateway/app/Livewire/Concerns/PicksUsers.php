<?php

namespace App\Livewire\Concerns;

use App\Services\AuthSession;
use App\Services\Clients\IdentityClient;

trait PicksUsers
{
    public const MAX_INVITEES = 20;

    public const MIN_SEARCH_LENGTH = 2;

    public string $search = '';

    public array $results = [];

    public array $invitees = [];

    public function updatedSearch(): void
    {
        $query = trim($this->search);

        if (mb_strlen($query) < self::MIN_SEARCH_LENGTH) {
            $this->results = [];
            unset($this->unavailable['search']);

            return;
        }

        $excluded = array_merge($this->excludedUserIds(), array_column($this->invitees, 'id'));

        $this->results = array_values(array_filter(
            $this->fromService('search', fn () => app(IdentityClient::class)->searchUsers(mb_substr($query, 0, 100)), []),
            fn (array $user) => ! in_array($user['id'], $excluded, true),
        ));
    }

    public function addInvitee(string $userId): void
    {
        $user = collect($this->results)->firstWhere('id', $userId);

        if ($user === null || count($this->invitees) >= self::MAX_INVITEES) {
            return;
        }

        $this->invitees[] = ['id' => $user['id'], 'name' => $user['name'], 'avatar' => $user['avatar'] ?? null];
        $this->search = '';
        $this->results = [];
        $this->resetErrorBag(['invitees', 'user_ids']);
    }

    public function removeInvitee(string $userId): void
    {
        $this->invitees = array_values(array_filter($this->invitees, fn (array $user) => $user['id'] !== $userId));
    }

    protected function inviteeIds(): array
    {
        return array_column($this->invitees, 'id');
    }

    protected function excludedUserIds(): array
    {
        return array_filter([app(AuthSession::class)->user()['id'] ?? null]);
    }
}
