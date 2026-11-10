# Engagement API

Port **8003**. Ratings, reviews, watchlist, watch history, custom lists and moderation. Follows
`docs/api/conventions.md`.

## Ratings

One rating per user and movie, with a score from 1 to 5.

### POST /api/ratings

Creates or updates the current user's rating of a movie (one rating per user and movie). `created_at` stays the date
of the first rating.

**Who:** user

**Request**

| Field | Type | Rules |
|---|---|---|
| `movie_id` | uuid | required, Catalog `movies.id` |
| `score` | integer | required, 1–5 |

**Response** `200`

```json
{"data": {"id": "0193a7d0-5555-7aaa-8bbb-000000000300", "movie_id": "0193a7c1-3333-7aaa-8bbb-000000000100", "score": 4, "created_at": "2026-11-03T18:20:00Z"}}
```

**Errors:** `401` no or invalid token · `422` invalid `movie_id` or `score`

**Events:** publishes `RatingAdded`, also when the rating is changed

### GET /api/movies/{id}/rating-summary

The average score of a movie, its number of ratings and the current user's score. A movie without ratings answers
`average: null` and `count: 0`.

**Who:** guest. With a valid token, `my_score` holds the user's score; without one, or with an invalid or expired
token, `my_score` is `null`.

**Response** `200`, `average` rounded to 2 decimals

```json
{"data": {"average": 4.25, "count": 12, "my_score": 4}}
```

**Errors:** `422` `id` is not a UUID

## Reviews

One review per user and movie. Public lists only show reviews with `is_hidden = false` (admin moderation) and
`author_blocked = false` (set by `user.blocked`); the author can still edit or delete a hidden review.

A review:

| Field | Type | Content |
|---|---|---|
| `id` | uuid | |
| `user_id` | uuid | the author (Identity `users.id`) |
| `movie_id` | uuid | Catalog `movies.id` |
| `body` | string | 10–2000 characters |
| `author_score` | integer \| null | the author's rating of the same movie (1–5), `null` if they did not rate it |
| `sentiment_label` | string \| null | `positive`, `neutral` or `negative`, set by Intelligence a few seconds after writing; `null` until then |
| `sentiment_score` | number \| null | 0–1, confidence of `sentiment_label` |
| `created_at`, `updated_at` | date | |

### GET /api/movies/{id}/reviews

A movie's reviews, newest first.

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
    {"id": "0193a7d1-6666-7aaa-8bbb-000000000400", "user_id": "0193a7c0-1111-7aaa-8bbb-000000000001", "movie_id": "0193a7c1-3333-7aaa-8bbb-000000000100", "body": "Loved the ending, the soundtrack is amazing.", "author_score": 5, "sentiment_label": "positive", "sentiment_score": 0.9731, "created_at": "2026-11-03T18:20:00Z", "updated_at": "2026-11-03T18:20:00Z"}
  ],
  "meta": {"current_page": 1, "last_page": 1, "total": 1}
}
```

**Errors:** `422` `id` is not a UUID

### GET /api/users/{id}/reviews

A user's reviews, newest first. Same request and response as the movie list.

**Who:** guest

**Errors:** `422` `id` is not a UUID

### POST /api/reviews

Writes the current user's review of a movie.

**Who:** user

**Request**

| Field | Type | Rules |
|---|---|---|
| `movie_id` | uuid | required, Catalog `movies.id` |
| `body` | string | required, 10–2000 characters, not only spaces |

**Response** `201`, the review

**Errors:** `401` no or invalid token · `422` invalid `movie_id` or `body`, or `movie_id`: `"You already reviewed this movie."`

**Events:** publishes `ReviewCreated`

### PUT /api/reviews/{id}

Changes the text of the current user's review.

**Who:** user, the author only

**Request**

| Field | Type | Rules |
|---|---|---|
| `body` | string | required, 10–2000 characters, not only spaces |

**Response** `200`, the review

**Errors:** `401` no or invalid token · `403` not the author · `404` `"Review not found."` · `422` invalid `body`

**Events:** publishes `ReviewUpdated`, only when the text changed

### DELETE /api/reviews/{id}

Deletes the current user's review.

**Who:** user, the author only

**Response** `204`

**Errors:** `401` no or invalid token · `403` not the author · `404` `"Review not found."`

## Watchlist

Every route works on the token's user only: nobody can see or change another user's watchlist.

A watchlist item:

| Field | Type | Content |
|---|---|---|
| `id` | uuid | |
| `movie_id` | uuid | Catalog `movies.id`; get the movie card with Catalog `GET /api/movies?ids=` |
| `created_at` | date | when the movie was added |

### GET /api/watchlist

Lists the current user's watchlist, newest first.

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
    {"id": "0193a7d0-5555-7aaa-8bbb-000000000300", "movie_id": "0193a7c1-3333-7aaa-8bbb-000000000100", "created_at": "2026-11-03T18:20:00Z"}
  ],
  "meta": {"current_page": 1, "last_page": 1, "total": 1}
}
```

**Errors:** `401` no or invalid token

### POST /api/watchlist

Adds a movie to the current user's watchlist. Adding a movie that is already there returns the existing item without
an error, so the button can be clicked twice.

**Who:** user

**Request**

| Field | Type | Rules |
|---|---|---|
| `movie_id` | uuid | required, Catalog `movies.id` |

**Response** `201` when added, `200` when it was already there

```json
{"data": {"id": "0193a7d0-5555-7aaa-8bbb-000000000300", "movie_id": "0193a7c1-3333-7aaa-8bbb-000000000100", "created_at": "2026-11-03T18:20:00Z"}}
```

**Errors:** `401` no or invalid token · `422` missing or invalid `movie_id`

### DELETE /api/watchlist/{movie_id}

Removes a movie from the current user's watchlist. The path holds the Catalog movie id, not the item id. Also answers
`204` when the movie was not there.

**Who:** user

**Response** `204`

**Errors:** `401` no or invalid token · `422` `movie_id` is not a UUID

### GET /api/watchlist/contains

Which of these movies are in the current user's watchlist, to show the button states on a grid with one call.

**Who:** user

**Request**

| Field | Type | Rules |
|---|---|---|
| `ids` | string | query, required, comma-separated UUIDs, max 100 |

**Response** `200`, the ids that are in the watchlist

```json
{"data": ["0193a7c1-3333-7aaa-8bbb-000000000100"]}
```

**Errors:** `401` no or invalid token · `422` missing or invalid `ids`, or more than 100

## Lists

Custom lists like "Favorite Comedies". Private by default. Everybody sees a list that is public and whose author is not
blocked (`author_blocked`, never returned); the owner always sees their own. Everyone else gets a `404`, the same as an
unknown id, so nobody can tell that a private list exists. Only the owner can change or delete a list, an admin
included.

A list:

| Field | Type | Content |
|---|---|---|
| `id` | uuid | |
| `user_id` | uuid | the owner, Identity `users.id` |
| `title` | string | |
| `description` | string \| null | |
| `is_public` | boolean | |
| `contains_movie` | boolean | only on `GET /api/lists?movie_id=` |
| `movie_ids` | uuid[] | only on `GET /api/lists/{id}`: Catalog `movies.id`, in the order they were added |
| `created_at` | date | |
| `updated_at` | date | |

### GET /api/lists

The current user's lists, public and private, newest first. With `movie_id`, each list also has `contains_movie`, for
the "Add to list" dropdown.

**Who:** user

**Request**

| Field | Type | Rules |
|---|---|---|
| `movie_id` | uuid | query, optional, Catalog `movies.id` |
| `page` | integer | query, default 1 |
| `per_page` | integer | query, default 20, max 100 |

**Response** `200`

```json
{
  "data": [
    {"id": "0193a7d2-7777-7aaa-8bbb-000000000500", "user_id": "0193a7c0-1111-7aaa-8bbb-000000000001", "title": "Best of Nolan", "description": "Mind benders", "is_public": true, "contains_movie": true, "created_at": "2026-11-03T18:20:00Z", "updated_at": "2026-11-03T18:20:00Z"}
  ],
  "meta": {"current_page": 1, "last_page": 1, "total": 1}
}
```

**Errors:** `401` no or invalid token · `422` `movie_id` is not a UUID

### POST /api/lists

Creates a list for the current user and publishes `ListCreated` (`docs/events.md`), for public and private lists alike:
Social ignores the private ones.

**Who:** user

**Request**

| Field | Type | Rules |
|---|---|---|
| `title` | string | required, max 150 |
| `description` | string \| null | optional, max 500 |
| `is_public` | boolean | optional, default `false` |

**Response** `201`: `{"data": <a list>}`

**Errors:** `401` no or invalid token · `422` invalid fields

### GET /api/lists/{id}

One list with the ids of its movies. Get the movie cards with Catalog `GET /api/movies?ids=`, which takes at most 100
ids per call.

**Who:** guest (the owner's token also shows their private list)

**Response** `200`

```json
{
  "data": {"id": "0193a7d2-7777-7aaa-8bbb-000000000500", "user_id": "0193a7c0-1111-7aaa-8bbb-000000000001", "title": "Best of Nolan", "description": "Mind benders", "is_public": true, "movie_ids": ["0193a7c1-3333-7aaa-8bbb-000000000100"], "created_at": "2026-11-03T18:20:00Z", "updated_at": "2026-11-03T18:20:00Z"}
}
```

**Errors:** `404` `"List not found."` (unknown id, not a UUID, or a list the caller may not see)

### PUT /api/lists/{id}

Changes a list. A field left out keeps its value.

**Who:** owner

**Request**

| Field | Type | Rules |
|---|---|---|
| `title` | string | optional, not empty, max 150 |
| `description` | string \| null | optional, max 500, `null` clears it |
| `is_public` | boolean | optional |

**Response** `200`: `{"data": <a list>}`

**Errors:** `401` no or invalid token · `403` not the owner · `404` `"List not found."` · `422` invalid fields

### DELETE /api/lists/{id}

Deletes a list and its items.

**Who:** owner

**Response** `204`

**Errors:** `401` no or invalid token · `403` not the owner · `404` `"List not found."`

### POST /api/lists/{id}/movies

Adds a movie to a list and updates the list's `updated_at`. Catalog is not called, so the id is not checked against it.

**Who:** owner

**Request**

| Field | Type | Rules |
|---|---|---|
| `movie_id` | uuid | required, Catalog `movies.id`, not already in this list |

**Response** `201`

```json
{
  "data": {"list_id": "0193a7d2-7777-7aaa-8bbb-000000000500", "movie_id": "0193a7c1-3333-7aaa-8bbb-000000000100", "created_at": "2026-11-03T18:20:00Z"}
}
```

**Errors:** `401` no or invalid token · `403` not the owner · `404` `"List not found."` · `422` `movie_id` is missing or
not a UUID, or `"Already in this list"` (`errors.movie_id`)

### DELETE /api/lists/{id}/movies/{movie_id}

Removes a movie from a list and updates the list's `updated_at`. A movie that is not in the list gives `204` too and
changes nothing.

**Who:** owner

**Response** `204`

**Errors:** `401` no or invalid token · `403` not the owner · `404` `"List not found."` · `422` `movie_id` is not a UUID

### GET /api/users/{id}/lists

A user's public lists (not those hidden because the author is blocked), newest first, for the public profile. The same
for everyone, including that user. An unknown user gives an empty page: users live in Identity.

**Who:** guest

**Request:** `page`, `per_page` as above

**Response** `200`: a page of lists, same shape as `GET /api/lists` without `contains_movie`

**Errors:** `404` the id is not a UUID

## History

Every route works on the token's user only: nobody can see or change another user's history. A movie can be watched
again, so it can have several entries.

A history entry:

| Field | Type | Content |
|---|---|---|
| `id` | uuid | |
| `movie_id` | uuid | Catalog `movies.id`; get the movie card with Catalog `GET /api/movies?ids=` |
| `watched_at` | date | when the user watched it |

### GET /api/history

Lists the current user's history, newest `watched_at` first.

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
    {"id": "0193a7d0-5555-7aaa-8bbb-000000000400", "movie_id": "0193a7c1-3333-7aaa-8bbb-000000000100", "watched_at": "2026-11-02T21:00:00Z"}
  ],
  "meta": {"current_page": 1, "last_page": 1, "total": 1}
}
```

**Errors:** `401` no or invalid token

### POST /api/history

Marks a movie as watched: adds a history entry and removes the movie from the watchlist, in one transaction.

**Who:** user

**Request**

| Field | Type | Rules |
|---|---|---|
| `movie_id` | uuid | required, Catalog `movies.id` |
| `watched_at` | date | optional, default now, cannot be in the future |

**Response** `201`

```json
{"data": {"id": "0193a7d0-5555-7aaa-8bbb-000000000400", "movie_id": "0193a7c1-3333-7aaa-8bbb-000000000100", "watched_at": "2026-11-02T21:00:00Z"}}
```

**Errors:** `401` no or invalid token · `422` invalid `movie_id` or `watched_at`

**Events:** publishes `MovieWatched`

### GET /api/history/contains

The latest `watched_at` of each of these movies the current user has watched, to show 'Watched on <date>' with one
call. Movies that were not watched are left out.

**Who:** user

**Request**

| Field | Type | Rules |
|---|---|---|
| `ids` | string | query, required, comma-separated UUIDs, max 100 |

**Response** `200`

```json
{"data": [{"movie_id": "0193a7c1-3333-7aaa-8bbb-000000000100", "watched_at": "2026-11-02T21:00:00Z"}]}
```

**Errors:** `401` no or invalid token · `422` missing or invalid `ids`, or more than 100
