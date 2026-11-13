<?php

namespace App\Services;

use App\Integration\Events\UserFollowed;
use App\Models\Follow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use MoviePlatform\Common\Auth\JwtUser;
use MoviePlatform\Common\Integration\EventPublisher;

class FollowService
{
    public function __construct(private EventPublisher $events) {}

    public function follow(JwtUser $follower, string $userId): Follow
    {
        if ($follower->id === $userId) {
            throw ValidationException::withMessages(['user_id' => ['You cannot follow yourself.']]);
        }

        $follow = new Follow(['follower_id' => $follower->id, 'following_id' => $userId, 'created_at' => Carbon::now()]);

        // One atomic statement: two requests at the same time can't both follow.
        if (Follow::query()->insertOrIgnore($follow->getAttributes()) === 0) {
            throw ValidationException::withMessages(['user_id' => ['You are already following this user.']]);
        }

        $this->events->publish(UserFollowed::ROUTING_KEY, UserFollowed::data($follow, $follower->name));

        return $follow;
    }

    public function unfollow(string $followerId, string $userId): void
    {
        Follow::followedBy($followerId)->where('following_id', $userId)->delete();
    }

    /** The users following $userId, newest first, as user_id + created_at. */
    public function followers(string $userId): Builder
    {
        return Follow::followersOf($userId)
            ->select(['follower_id as user_id', 'created_at'])
            ->orderByDesc('created_at')
            ->orderBy('follower_id');
    }

    /** The users $userId follows, newest first, as user_id + created_at. */
    public function following(string $userId): Builder
    {
        return Follow::followedBy($userId)
            ->select(['following_id as user_id', 'created_at'])
            ->orderByDesc('created_at')
            ->orderBy('following_id');
    }

    /** @return array{followers: int, following: int, is_following: bool|null} */
    public function stats(string $userId, ?string $viewerId): array
    {
        return [
            'followers' => Follow::followersOf($userId)->count(),
            'following' => Follow::followedBy($userId)->count(),
            'is_following' => $viewerId === null
                ? null
                : Follow::followedBy($viewerId)->where('following_id', $userId)->exists(),
        ];
    }

    /**
     * Whether $viewerId follows each of $userIds, in the order given, with one query.
     *
     * @param  string[]  $userIds
     * @return list<array{user_id: string, is_following: bool}>
     */
    public function statuses(string $viewerId, array $userIds): array
    {
        $followed = Follow::followedBy($viewerId)
            ->whereIn('following_id', array_unique($userIds))
            ->pluck('following_id')
            ->map(fn (string $id) => strtolower($id))
            ->flip();

        return array_map(fn (string $id) => [
            'user_id' => $id,
            'is_following' => $followed->has(strtolower($id)),
        ], $userIds);
    }
}
