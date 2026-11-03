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

## Movie nights

A user hosts a movie night and invites others (1220). Only participants (the host and invited users, whatever their
answer) can see a night: anyone else gets `404`, so nobody can tell it exists. Ids of users and movies are Identity and
Catalog ids; the Gateway loads names, avatars and movie cards with their bulk lookups.

A movie night, as returned by the list:

| Field | Type | Content |
|---|---|---|
| `id` | uuid | |
| `host_id` | uuid | Identity user id of the host |
| `title` | string | |
| `description` | string \| null | |
| `scheduled_at` | date | UTC |
| `location` | string \| null | at least one of `location` and `meeting_link` is set |
| `meeting_link` | string \| null | an `http` or `https` URL |
| `status` | string | `open`, `finalized` or `cancelled` |
| `winner_movie_id` | uuid \| null | Catalog movie id, set when the night is finalized |
| `my_status` | string | the caller's participant status: `pending`, `accepted` or `declined` (the host is `accepted`) |
| `created_at` | date | |

`GET /api/movie-nights/{id}` and `POST /api/movie-nights` add:

| Field | Type | Content |
|---|---|---|
| `participants` | object[] | `user_id`, `status`, `is_host`, `responded_at` (null until they answer), in the order they were invited (the host first) |
| `candidates` | object[] | `id`, `movie_id`, `added_by`, `votes` (count), in the order they were proposed; empty until voting (1226) |
| `votes_cast` | integer | number of votes on the night |

### POST /api/movie-nights

Creates a movie night hosted by the caller, who is added as an `accepted` participant. Texts are trimmed; an empty
`description`, `location` or `meeting_link` is saved as `null`.

**Who:** user

**Request**

| Field | Type | Rules |
|---|---|---|
| `title` | string | required, max 100 |
| `description` | string \| null | optional, max 2000 |
| `scheduled_at` | date | required, in the future; an offset (`+02:00`) is converted to UTC |
| `location` | string \| null | required without `meeting_link`, max 255 |
| `meeting_link` | string \| null | required without `location`, `http`/`https` URL, max 255 |

**Response** `201`

```json
{"data": {"id": "0193a7d3-8888-7aaa-8bbb-000000000600", "host_id": "0193a7c0-1111-7aaa-8bbb-000000000001", "title": "Friday sci-fi", "description": null, "scheduled_at": "2026-11-07T19:00:00Z", "location": null, "meeting_link": "https://meet.example.com/abc", "status": "open", "winner_movie_id": null, "my_status": "accepted", "created_at": "2026-11-03T18:00:00Z", "participants": [{"user_id": "0193a7c0-1111-7aaa-8bbb-000000000001", "status": "accepted", "is_host": true, "responded_at": "2026-11-03T18:00:00Z"}], "candidates": [], "votes_cast": 0}}
```

**Errors:** `401` no or invalid token · `422` invalid fields, `scheduled_at` not in the future (`"The movie night must
be in the future."`), or neither `location` nor `meeting_link` (`"Give a location or a meeting link."` on both)

### GET /api/movie-nights

The caller's movie nights, hosted and invited (any answer), soonest first, past ones included.

**Who:** user

**Request**

| Field | Type | Rules |
|---|---|---|
| `page` | integer | query, default 1 |
| `per_page` | integer | query, default 20, max 100 |

**Response** `200`

```json
{
  "data": [
    {"id": "0193a7d3-8888-7aaa-8bbb-000000000600", "host_id": "0193a7c0-2222-7aaa-8bbb-000000000002", "title": "Friday sci-fi", "description": null, "scheduled_at": "2026-11-07T19:00:00Z", "location": "Maria's place", "meeting_link": null, "status": "open", "winner_movie_id": null, "my_status": "pending", "created_at": "2026-11-03T18:00:00Z"}
  ],
  "meta": {"current_page": 1, "last_page": 1, "total": 1}
}
```

**Errors:** `401` no or invalid token

### GET /api/movie-nights/{id}

One movie night with its participants, candidates and votes.

**Who:** user, participant of the night

**Response** `200`, same body as `POST /api/movie-nights`

**Errors:** `401` no or invalid token · `404` `"Movie night not found."` (unknown id, or the caller is not a
participant) · `404` `"Not found."` (`id` is not a UUID)

### POST /api/movie-nights/{id}/invites

The host invites users to an open movie night. Each new user becomes a `pending` participant. The host and users
already invited (whatever their answer) are skipped without an error, so sending the same list twice changes nothing.
Social does not check that the users exist: the Gateway picks them with Identity's user search.

**Who:** user, host of the night

**Request**

| Field | Type | Rules |
|---|---|---|
| `user_ids` | uuid[] | required, 1 to 20 Identity user ids, no id twice (in any letter case) |

**Response** `200`, the night as in `GET /api/movie-nights/{id}`, with the new participants

**Errors:** `401` no or invalid token · `403` `"Only the host can invite people."` (a participant who is not the host) ·
`404` `"Movie night not found."` (unknown id, or the caller is not a participant) · `422` `"This movie night is no
longer open."` (finalized or cancelled) · `422` `user_ids` missing, empty or more than 20 (`errors.user_ids`), or an id
that is not a UUID or is repeated (`errors.user_ids.<position>`)

**Events:** publishes one `MovieNightInviteSent` per new participant, after the invites are saved (`host_name` is the
`name` claim of the host's token)
