# Social API

Port **8004**. Follows, profiles, privacy, the activity feed, movie nights and chat rooms. Follows
`docs/api/conventions.md`.

## Follows

Social stores only user ids (Identity `users.id`). It does not check that a user exists and never calls Identity: the
Gateway loads names and avatars with Identity's bulk lookup.

A `{userId}` / `{id}` that is not a UUID answers `422` with `errors.user_id`.

### POST /api/follow/{userId}

The current user starts following `userId`.

**Who:** user

**Response** `201`

```json
{"data": {"follower_id": "0193a7c0-1111-7aaa-8bbb-000000000001", "following_id": "0193a7c0-2222-7aaa-8bbb-000000000002", "created_at": "2026-11-03T18:20:00Z"}}
```

**Errors:** `401` no or invalid token · `422` `userId` is not a UUID, is the current user (`"You cannot follow
yourself."`) or is already followed (`"You are already following this user."`), all under `errors.user_id`

**Events:** publishes `UserFollowed` (`follower_name` is the `name` claim of the caller's token)

### DELETE /api/follow/{userId}

The current user stops following `userId`. Also `204` when they were not following.

**Who:** user

**Response** `204`, no body

**Errors:** `401` no or invalid token · `422` `userId` is not a UUID

### GET /api/users/{id}/followers

The users following `id`, newest first.

**Who:** guest

**Request**

| Field | Type | Rules |
|---|---|---|
| `page` | integer | query, default 1 |
| `per_page` | integer | query, default 20, max 100 |

**Response** `200`

```json
{
  "data": [
    {"user_id": "0193a7c0-3333-7aaa-8bbb-000000000003", "followed_at": "2026-11-03T18:20:00Z"}
  ],
  "meta": {"current_page": 1, "last_page": 1, "total": 1}
}
```

**Errors:** `422` `id` is not a UUID

### GET /api/users/{id}/following

The users `id` follows, newest first. Same request and response as `/followers`: `user_id` is the followed user.

**Who:** guest

**Errors:** `422` `id` is not a UUID

### GET /api/users/{id}/follow-stats

Counts for a profile, and whether the current user follows `id`.

**Who:** guest (a valid token adds `is_following`; a missing or invalid token is treated as a guest)

**Response** `200`

```json
{"data": {"followers": 12, "following": 5, "is_following": true}}
```

`is_following` is `null` for guests.

**Errors:** `422` `id` is not a UUID
