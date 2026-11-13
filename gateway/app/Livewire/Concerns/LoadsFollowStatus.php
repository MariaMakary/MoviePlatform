<?php

namespace App\Livewire\Concerns;

use App\Services\AuthSession;
use App\Services\Clients\SocialClient;

/**
 * One Social call for the Follow buttons of a list of people, instead of one follow-stats call per button.
 * Needs WithServiceFallback; a failure marks the 'follow' section unavailable.
 */
trait LoadsFollowStatus
{
    /**
     * @param  array<array{id: string}>  $users
     * @return array<string, bool>|null  user id => is_following; [] for guests; null when Social is down
     */
    protected function followStatus(array $users): ?array
    {
        $me = app(AuthSession::class)->user();
        $ids = array_values(array_diff(array_column($users, 'id'), [$me['id'] ?? null]));

        if ($me === null || $ids === []) {
            return [];
        }

        $statuses = $this->fromService('follow', fn () => app(SocialClient::class)->followStatus($ids));

        return $statuses === null ? null : array_column($statuses, 'is_following', 'user_id');
    }
}
