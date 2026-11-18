<?php

namespace App\Services;

use App\Integration\Events\UserBlocked;
use App\Integration\Events\UserDeleted;
use App\Integration\Events\UserUnblocked;
use App\Models\PasswordResetToken;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use MoviePlatform\Common\Auth\JwtUser;
use MoviePlatform\Common\Integration\EventPublisher;

/**
 * User management by admins (1271). Each change locks the user row for its transaction and publishes its event after
 * the commit, only when something changed, so a double click never sends two events.
 */
class AdminUserService
{
    public function __construct(private EventPublisher $events, private ProfileService $profiles) {}

    /** Every user, blocked ones and admins included, newest first; $query matches any part of the name or email. */
    public function list(?string $query): Builder
    {
        $users = User::query()->latest()->orderByDesc('id');
        $query = trim($query ?? '');

        if ($query !== '') {
            $pattern = '%'.addcslashes($query, '%_\\').'%';
            $users->where(fn (Builder $where) => $where->where('name', 'ilike', $pattern)->orWhere('email', 'ilike', $pattern));
        }

        return $users;
    }

    public function setBlocked(JwtUser $admin, string $id, bool $blocked): User
    {
        if ($blocked) {
            $this->ensureNotSelf($admin, $id, 'You cannot block your own account.');
        }

        [$user, $changed] = DB::transaction(function () use ($id, $blocked) {
            $user = User::whereKey($id)->lockForUpdate()->firstOrFail();

            if ($user->is_blocked === $blocked) {
                return [$user, false];
            }

            $user->is_blocked = $blocked;
            $user->save();

            return [$user, true];
        });

        if ($changed) {
            $blocked
                ? $this->events->publish(UserBlocked::ROUTING_KEY, UserBlocked::data($user))
                : $this->events->publish(UserUnblocked::ROUTING_KEY, UserUnblocked::data($user));
        }

        return $user;
    }

    /** Deletes the account, its favorite genres (cascade), a pending password reset and its uploaded avatar file. */
    public function delete(JwtUser $admin, string $id): void
    {
        $this->ensureNotSelf($admin, $id, 'You cannot delete your own account.');

        $user = DB::transaction(function () use ($id) {
            $user = User::whereKey($id)->lockForUpdate()->firstOrFail();

            PasswordResetToken::whereKey($user->email)->delete();
            $user->delete();

            return $user;
        });

        // After the commit: a rolled back delete keeps its avatar.
        $this->profiles->deleteAvatarFile($user->avatar);
        $this->events->publish(UserDeleted::ROUTING_KEY, UserDeleted::data($user));
    }

    private function ensureNotSelf(JwtUser $admin, string $id, string $message): void
    {
        if (strtolower($id) === strtolower($admin->id)) {
            abort(422, $message);
        }
    }
}
