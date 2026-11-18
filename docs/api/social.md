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

### GET /api/follow/status?ids={id},{id},…

Whether the current user follows each of the given users, in one query. Lets a page of people (followers list, people
search) load every Follow button with one call instead of one `follow-stats` call per row.

**Who:** user

**Query:** `ids`: comma-separated user UUIDs, 1 to 100 (repeats allowed)

**Response** `200`: one entry per id, in the same order as `ids`

```json
{"data": [{"user_id": "0193a7c0-2222-7aaa-8bbb-000000000002", "is_following": true}, {"user_id": "0193a7c0-3333-7aaa-8bbb-000000000003", "is_following": false}]}
```

**Errors:** `401` no or invalid token · `422` `ids` missing, more than 100, or one is not a UUID

## Privacy

What a user shows on their public profile. Every setting is `true` (shown) until the user saves their own choice: a
user who never saved gets the defaults, and the row is only created on the first `PUT`. The services that own the
data (reviews and ratings, lists, history) do not read these settings; the Gateway hides the sections on the profile.

Settings, as returned by every endpoint here:

| Field | Type | Content |
|---|---|---|
| `user_id` | uuid | Identity `users.id` |
| `show_reviews` | boolean | default `true` |
| `show_ratings` | boolean | default `true` |
| `show_lists` | boolean | default `true` |
| `show_history` | boolean | default `true` (watch history) |

### GET /api/privacy

The current user's settings.

**Who:** user

**Response** `200`

```json
{"data": {"user_id": "0193a7c0-1111-7aaa-8bbb-000000000001", "show_reviews": true, "show_ratings": true, "show_lists": true, "show_history": false}}
```

**Errors:** `401` no or invalid token

### PUT /api/privacy

Replaces the current user's settings.

**Who:** user

**Request**

| Field | Type | Rules |
|---|---|---|
| `show_reviews` | boolean | required |
| `show_ratings` | boolean | required |
| `show_lists` | boolean | required |
| `show_history` | boolean | required |

**Response** `200` (also on the first save), the new settings as in `GET /api/privacy`

**Errors:** `401` no or invalid token · `422` a field missing or not a boolean

### GET /api/users/{id}/privacy

Another user's settings, for their public profile. An unknown user gets the defaults (Social does not check that a
user exists).

**Who:** guest

**Response** `200`, as in `GET /api/privacy`

**Errors:** `422` `id` is not a UUID (`errors.user_id`)

## Activity feed

Built from Engagement's events (`docs/events.md`), never from Engagement's database: every rating, review, watch and
public list becomes an entry. A changed rating updates its entry (new score, moved up) and an edited review updates its
excerpt; every watch is a new entry; private lists never appear. A movie deleted from Catalog removes its entries.

A user blocked in Identity (`UserBlocked`) disappears from every feed and their own activity page, even for
themselves, until `UserUnblocked`; their entries are kept, only hidden. A deleted user (`UserDeleted`) loses everything
Social keeps about them: follows both ways, privacy settings, activity, chat messages, votes, invitations and the movie
nights they hosted (with those nights' candidates, votes and participants). Movies they proposed on other people's
nights stay, since others may have voted for them.

An entry is shown to other users only when the author's privacy setting for its type is on (`GET /api/privacy`):

| `type` | `subject_id` | `data` | Shown when |
|---|---|---|---|
| `rating` | Engagement `ratings.id` | `{"score": 4}` | `show_ratings` |
| `review` | Engagement `reviews.id` | `{"excerpt": "first 280 characters…"}` | `show_reviews` |
| `watched` | the movie id (a watch has no id of its own) | `{"watched_at": "2026-11-15T21:00:00Z"}` | `show_history` |
| `list` | Engagement `lists.id` | `{"title": "Best of Nolan"}` | `show_lists` |

An entry:

| Field | Type | Content |
|---|---|---|
| `id` | uuid | |
| `user_id` | uuid | who did it (Identity `users.id`) |
| `type` | string | `rating`, `review`, `watched` or `list` |
| `subject_id` | uuid | see the table above |
| `movie_id` | uuid \| null | Catalog `movies.id`; `null` for a list |
| `data` | object | see the table above |
| `created_at` | date | when it happened (the event's `occurred_at`; for a changed rating, the last change) |

### GET /api/feed

What the users the current user follows did, newest first, as each author's privacy allows. The user's own activity
is not in it.

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
    {"id": "0193a7d3-8888-7aaa-8bbb-000000000600", "user_id": "0193a7c0-2222-7aaa-8bbb-000000000002", "type": "review", "subject_id": "0193a7d1-6666-7aaa-8bbb-000000000400", "movie_id": "0193a7c1-3333-7aaa-8bbb-000000000100", "data": {"excerpt": "Loved the ending, the soundtrack is amazing."}, "created_at": "2026-11-16T11:00:00Z"}
  ],
  "meta": {"current_page": 1, "last_page": 1, "total": 1}
}
```

Get the movie cards with Catalog `GET /api/movies?ids=` and the authors with Identity `GET /api/users?ids=`.

**Errors:** `401` no or invalid token

### GET /api/users/{id}/activity

One user's activity for their profile, newest first. Others see what the user's privacy allows; the user sees all of
their own (with a token).

**Who:** guest

**Request:** `page`, `per_page` as above

**Response** `200`, as in `GET /api/feed`

**Errors:** `422` `id` is not a UUID (`errors.user_id`)

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
| `candidates` | object[] | the shortlist: `id`, `movie_id`, `added_by`, `votes` (count), in the order they were proposed |
| `votes_cast` | integer | number of votes on the night |
| `my_vote` | uuid \| null | the candidate `id` the caller voted for, `null` before voting |

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
{"data": {"id": "0193a7d3-8888-7aaa-8bbb-000000000600", "host_id": "0193a7c0-1111-7aaa-8bbb-000000000001", "title": "Friday sci-fi", "description": null, "scheduled_at": "2026-11-07T19:00:00Z", "location": null, "meeting_link": "https://meet.example.com/abc", "status": "open", "winner_movie_id": null, "my_status": "accepted", "created_at": "2026-11-03T18:00:00Z", "participants": [{"user_id": "0193a7c0-1111-7aaa-8bbb-000000000001", "status": "accepted", "is_host": true, "responded_at": "2026-11-03T18:00:00Z"}], "candidates": [], "votes_cast": 0, "my_vote": null}}
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

### PATCH /api/movie-nights/{id}/rsvp

An invited user accepts or declines. The answer can be changed (declined → accepted and back) while the night is
`open`. Each change sets `responded_at`. Sending the answer the user already gave changes nothing and publishes no
event, so the host is not told twice.

**Who:** user, invited to the night (not the host)

**Request**

| Field | Type | Rules |
|---|---|---|
| `status` | string | required, `accepted` or `declined` |

**Response** `200`, the night as in `GET /api/movie-nights/{id}`, with the new `my_status`

**Errors:** `401` no or invalid token · `404` `"Movie night not found."` (unknown id, or the caller is not invited) ·
`422` `"The host is always going."` (the host) · `422` `"This movie night is no longer open."` (finalized or
cancelled) · `422` `status` missing or not `accepted` / `declined` (`errors.status`)

**Events:** publishes `MovieNightInviteAnswered` after the answer is saved, only when it changed (`user_name` is the
`name` claim of the caller's token)

### Shortlist and voting

Participants who accepted propose movies (candidates) and vote while the night is `open`. Each participant has one
vote per night and can move it to another candidate. Social does not check that a movie exists: the Gateway picks it
with Catalog's search. Every change is broadcast live as `VotesUpdated` (see "Real time" below).

Order of checks for the three endpoints: the caller is not a participant → `404`, then the role check → `403`, then
the night is not `open` → `422`, then the fields.

### POST /api/movie-nights/{id}/candidates

Adds a movie to the shortlist.

**Who:** user, participant of the night who accepted (the host included)

**Request**

| Field | Type | Rules |
|---|---|---|
| `movie_id` | uuid | required, Catalog movie id, not already on this night's shortlist |

**Response** `201`, the night as in `GET /api/movie-nights/{id}`, with the new candidate (`votes: 0`)

**Errors:** `401` no or invalid token · `403` `"Accept the invitation first."` (pending or declined) · `404` `"Movie
night not found."` (unknown id, or the caller is not a participant) · `422` `"This movie night is no longer open."`
(finalized or cancelled) · `422` `movie_id` missing or not a UUID, or `"This movie is already on the shortlist."`
(`errors.movie_id`)

**Real time:** broadcasts `VotesUpdated`

### DELETE /api/movie-nights/{id}/candidates/{candidateId}

Removes a movie from the shortlist, with its votes: those voters can vote again.

**Who:** user, the participant who added the movie, or the host

**Response** `200`, the night as in `GET /api/movie-nights/{id}`, without the candidate

**Errors:** `401` no or invalid token · `403` `"Only the host or the person who added this movie can remove it."` ·
`404` `"Movie night not found."` (unknown id, or the caller is not a participant) · `404` `"Candidate not found."`
(unknown id or a candidate of another night) · `404` `"Not found."` (an id that is not a UUID) · `422` `"This movie
night is no longer open."`

**Real time:** broadcasts `VotesUpdated`

### POST /api/movie-nights/{id}/votes

Casts the caller's vote, or moves it to another candidate. Voting for the candidate already chosen changes nothing and
broadcasts nothing.

**Who:** user, participant of the night who accepted (the host included)

**Request**

| Field | Type | Rules |
|---|---|---|
| `candidate_id` | uuid | required, a candidate of this night |

**Response** `200`, the night as in `GET /api/movie-nights/{id}`, with the new `my_vote` and counts

**Errors:** `401` no or invalid token · `403` `"Accept the invitation first."` (pending or declined) · `404` `"Movie
night not found."` (unknown id, or the caller is not a participant) · `422` `"This movie night is no longer open."` ·
`422` `candidate_id` missing or not a UUID, or `"This movie is not on the shortlist of this movie night."`
(`errors.candidate_id`)

**Real time:** broadcasts `VotesUpdated` when the vote changed

### POST /api/movie-nights/{id}/finalize

Closes the vote. The winner is the candidate with the most votes. On a tie (a shortlist without votes is a tie too),
the host sends the `candidate_id` of one of the tied candidates. Sets `status` to `finalized` and `winner_movie_id`.
Social has no movie titles, so it asks Catalog (`GET /api/internal/movies`) for the winner's title before saving: if
Catalog is down, nothing changes.

**Who:** user, host of the night

**Request**

| Field | Type | Rules |
|---|---|---|
| `candidate_id` | uuid \| null | required on a tie; when sent, must be one of the candidates with the most votes |

**Response** `200`, the night as in `GET /api/movie-nights/{id}`, with `status: "finalized"` and `winner_movie_id`

**Errors:** `401` no or invalid token · `403` `"Only the host can finalize the movie night."` · `404` `"Movie night not
found."` (unknown id, or the caller is not a participant) · `422` `"This movie night is no longer open."` (already
finalized, or cancelled) · `422` `"Add a movie to the shortlist first."` (no candidates) · `422` `candidate_id` not a
UUID, `"It is a tie: choose one of the movies with the most votes."` or `"Choose one of the movies with the most
votes."` (`errors.candidate_id`) · `422` `"The winning movie is no longer in the catalog."` · `503` `"Catalog is
unavailable."`

**Events:** publishes `MovieNightFinalized` after the night is saved (`participant_ids`: participants who accepted,
without the host)

### POST /api/movie-nights/{id}/cancel

Cancels an open night: `status` becomes `cancelled`. Invites, RSVP, candidates, votes, finalize and cancel are then
refused with `422` `"This movie night is no longer open."`. No event: participants see the cancelled status on the
page and in their list.

**Who:** user, host of the night

**Response** `200`, the night as in `GET /api/movie-nights/{id}`, with `status: "cancelled"`

**Errors:** `401` no or invalid token · `403` `"Only the host can cancel the movie night."` · `404` `"Movie night not
found."` (unknown id, or the caller is not a participant) · `422` `"This movie night is no longer open."` (already
finalized or cancelled)

## Chat rooms

One public room per movie: every logged-in user can read it and write in it. A room is created the first time someone
opens it, named after the movie's title, which Social asks Catalog for (`GET /api/internal/movies`). Messages store
only the author's `user_id`: the Gateway loads names and avatars with Identity's bulk lookup.

A `{id}` or `{movieId}` that is not a UUID answers `404`.

### GET /api/rooms

Every room, the one with the latest message first. Rooms without messages come last, newest first.

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
    {"id": "0193a7e0-1111-7aaa-8bbb-000000000800", "movie_id": "0193a7c1-3333-7aaa-8bbb-000000000100", "name": "Inception", "last_message_at": "2026-11-15T12:00:00Z", "created_at": "2026-11-14T09:00:00Z"}
  ],
  "meta": {"current_page": 1, "last_page": 1, "total": 1}
}
```

`last_message_at` is `null` in a room without messages.

**Errors:** `401` no or invalid token

### GET /api/rooms/by-movie/{movieId}

The room of a movie, created if it does not exist yet. Opening it again (by anyone) gives the same room: `movie_id`
is unique. Catalog is only called when the room is created.

**Who:** user

**Response** `200`, one room as in `GET /api/rooms`

**Errors:** `401` no or invalid token · `404` `"Movie not found."` (Catalog does not have this movie) · `503`
`"Catalog is unavailable."` (only when the room did not exist yet)

### GET /api/rooms/{id}/messages?before={messageId}

The messages of a room, 50 at a time, **oldest first** inside the answer, so the page can append them as they come.

This endpoint uses a **cursor instead of page numbers**: new messages arrive all the time, so page 2 would shift
while the user scrolls. Without `before` you get the latest 50. To load older ones, send the `before` from the last
answer's `meta`: you get the 50 messages written just before that message. `meta.has_more` is `false` (and `before`
`null`) when there is nothing older.

**Who:** user

**Request**

| Field | Type | Rules |
|---|---|---|
| `before` | uuid | query, optional, the id of a message of this room |

**Response** `200`

```json
{
  "data": [
    {"id": "0193a7e1-1111-7aaa-8bbb-000000000900", "room_id": "0193a7e0-1111-7aaa-8bbb-000000000800", "user_id": "0193a7c0-2222-7aaa-8bbb-000000000002", "body": "What an ending!", "created_at": "2026-11-15T12:00:00Z"}
  ],
  "meta": {"has_more": true, "before": "0193a7e1-1111-7aaa-8bbb-000000000900"}
}
```

**Errors:** `401` no or invalid token · `404` `"Room not found."` · `422` `before` not a UUID, or `"This message is
not in this room."` (`errors.before`)

### POST /api/rooms/{id}/messages

Sends a message to the room. The body is trimmed before it is saved.

**Who:** user

**Request**

| Field | Type | Rules |
|---|---|---|
| `body` | string | required, not only spaces, max 1000 |

**Response** `201`, one message as in `GET /api/rooms/{id}/messages`

**Errors:** `401` no or invalid token · `404` `"Room not found."` · `422` `body` missing, empty or longer than 1000
characters (`errors.body`)

**Real time:** broadcasts `MessageSent` on `private-room.{id}` once the message is saved (see below)

## Admin

### GET /api/stats

Numbers for the admin dashboard (1269): the user activity chart and the most active users table. Grouped queries on
every call, no cache. Every row counts, the activity of blocked users included (like Engagement's `GET /api/stats`).

**Who:** admin

**Response** `200`

| Field | Type | Content |
|---|---|---|
| `activity_per_day` | object[] | exactly 30 `{date, count}`, the last 30 **UTC** days with today, oldest first; `count` = feed activities (ratings, reviews, watches, public lists) created that day, `0` for a day without any |
| `follows` | integer | follow relationships |
| `movie_nights` | object | `created`: every movie night, `finalized`: those with status `finalized` |
| `messages_last_7_days` | integer | chat messages sent in the last 7 × 24 hours |
| `most_active_users` | object[] | top 10 `{user_id, activities}` by feed activities of all time, most first; ties: smaller `user_id` first |

User ids are Identity ids: the Gateway loads names and avatars with Identity's bulk lookup.

```json
{
  "data": {
    "activity_per_day": [{"date": "2026-10-20", "count": 0}, {"date": "2026-10-21", "count": 4}, …, {"date": "2026-11-18", "count": 7}],
    "follows": 58,
    "movie_nights": {"created": 9, "finalized": 4},
    "messages_last_7_days": 31,
    "most_active_users": [{"user_id": "0193a7c0-1111-7aaa-8bbb-000000000001", "activities": 42}]
  }
}
```

**Errors:** `401` no or invalid token · `403` `"This action is unauthorized."` (not an admin)

## Real time

Social broadcasts through Reverb, which runs from the Gateway (`gateway/CLAUDE.md`, "Real time"). Channels are
private: the Gateway's `/broadcasting/auth` decides who may listen.

| Event (`broadcastAs`) | Channel | Sent | Payload |
|---|---|---|---|
| `VotesUpdated` | `private-movie-night.{id}` | after a candidate is added or removed, or a vote changes, once saved | `movie_night_id`, `totals`: `[{candidate_id, votes}]` for every candidate, in shortlist order |
| `MessageSent` | `private-room.{id}` | after a message is saved | `id`, `user_id`, `user_name` (the `name` claim of the sender's token), `body`, `created_at` |

```json
{"movie_night_id": "0193a7d3-8888-7aaa-8bbb-000000000600", "totals": [{"candidate_id": "0193a7d4-1111-7aaa-8bbb-000000000700", "votes": 2}, {"candidate_id": "0193a7d4-2222-7aaa-8bbb-000000000701", "votes": 0}]}
```

```json
{"id": "0193a7e1-1111-7aaa-8bbb-000000000900", "user_id": "0193a7c0-2222-7aaa-8bbb-000000000002", "user_name": "Joe", "body": "What an ending!", "created_at": "2026-11-15T12:00:00Z"}
```

A `candidate_id` the page does not know yet is a movie just added: reload the night to get its `movie_id`. If Reverb
is down the change is still saved and the endpoint answers normally; only the live update is lost (logged as a
warning).
