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
