# Catalog API

Port **8002**. Movies, genres and people, filled from TMDB. Follows `docs/api/conventions.md`.

Every other service refers to a movie by its Catalog `id` (UUID), never by `tmdb_id`.

## Movie cards

The short form of a movie, used wherever movies are listed (watchlist, history, lists, feeds, recommendations,
popular and upcoming). Built by `MovieCardResource`.

| Field | Type | Content |
|---|---|---|
| `id` | uuid | Catalog `movies.id` |
| `title` | string | |
| `poster_url` | string \| null | full TMDB image URL (w342), `null` when the movie has no poster |
| `year` | integer \| null | year of `release_date` |
| `release_date` | date (`YYYY-MM-DD`) \| null | e.g. to show when an upcoming movie comes out |
| `vote_average` | number \| null | TMDB average, 0–10, one decimal |
| `genres` | string[] | genre names, alphabetical; can be empty |

### GET /api/movies

Movie cards for a list of ids, in one call. For pages that already hold Catalog movie ids (a watchlist, a list, a
feed) and need the cards to show them.

Results come **in the order of the ids**. Unknown ids are skipped (a movie may have been deleted), so the result can
be shorter than the request. A repeated id is returned once. Not paginated.

**Who:** guest

**Request**

| Field | Type | Rules |
|---|---|---|
| `ids` | string | query, required, comma-separated Catalog movie ids, each a UUID, at most 100 |

`GET /api/movies?ids=0193a7c1-3333-7aaa-8bbb-000000000101,0193a7c1-3333-7aaa-8bbb-000000000100`

**Response** `200`

```json
{
  "data": [
    {"id": "0193a7c1-3333-7aaa-8bbb-000000000101", "title": "Interstellar", "poster_url": "https://image.tmdb.org/t/p/w342/gEU2QniE6E77NI6lCU6MxlNBvIx.jpg", "year": 2014, "release_date": "2014-11-05", "vote_average": 8.5, "genres": ["Adventure", "Drama", "Science Fiction"]},
    {"id": "0193a7c1-3333-7aaa-8bbb-000000000100", "title": "Inception", "poster_url": "https://image.tmdb.org/t/p/w342/oYuLEt3zVCKq57qu2F8dT7NIa6f.jpg", "year": 2010, "release_date": "2010-07-15", "vote_average": 8.4, "genres": ["Action", "Science Fiction"]}
  ]
}
```

**Errors:** `422` `ids` missing or empty, more than 100 ids, or an id that is not a UUID (`errors.ids` or
`errors.ids.<position>`)

### GET /api/movies/{id}

Everything the movie page shows, built by `MovieDetailResource`. `{id}` is the Catalog movie id (UUID).

A TMDB movie that was never synced (created from a list by popular or upcoming) or was last synced more than
**7 days** ago is first synced from TMDB (details, genres, cast, director, trailer). If TMDB is down or no longer has
the movie, the local copy is served as it is (the cast can then be empty) and the sync is tried again on the next
open. A movie added by an admin (no `tmdb_id`) is never synced, and an admin's edits are kept.

**Who:** guest

**Response** `200`

| Field | Type | Content |
|---|---|---|
| `id` | uuid | |
| `title` | string | |
| `overview` | string \| null | |
| `poster_url` | string \| null | full TMDB image URL (w342) |
| `backdrop_url` | string \| null | full TMDB image URL (w1280) |
| `year` | integer \| null | year of `release_date` |
| `release_date` | date (`YYYY-MM-DD`) \| null | |
| `runtime` | integer \| null | minutes |
| `vote_average` | number \| null | TMDB average, 0–10, one decimal |
| `genres` | `{id, name}[]` | Catalog genre ids, alphabetical |
| `directors` | `{id, name, photo_url}[]` | Catalog person ids, alphabetical; usually one |
| `cast` | `{id, name, character, photo_url}[]` | Catalog person ids, in billing order, at most 15 |
| `trailer_key` | string \| null | YouTube video key (`https://www.youtube.com/watch?v=<key>`) |

`photo_url` is a full TMDB image URL (w185) or `null`; `character` can be `null`.

```json
{
  "data": {
    "id": "0193a7c1-3333-7aaa-8bbb-000000000100",
    "title": "Inception",
    "overview": "Cobb, a skilled thief who commits corporate espionage by infiltrating the subconscious of his targets…",
    "poster_url": "https://image.tmdb.org/t/p/w342/oYuLEt3zVCKq57qu2F8dT7NIa6f.jpg",
    "backdrop_url": "https://image.tmdb.org/t/p/w1280/8ZTVqvKDQ8emSGUEMjsS4yHAwrp.jpg",
    "year": 2010,
    "release_date": "2010-07-15",
    "runtime": 148,
    "vote_average": 8.4,
    "genres": [{"id": "0193a7c1-4444-7aaa-8bbb-000000000001", "name": "Action"}],
    "directors": [{"id": "0193a7c1-5555-7aaa-8bbb-000000000001", "name": "Christopher Nolan", "photo_url": "https://image.tmdb.org/t/p/w185/xuAIuYSmsUzKlUMBFGVZaWsY3DZ.jpg"}],
    "cast": [{"id": "0193a7c1-5555-7aaa-8bbb-000000000002", "name": "Leonardo DiCaprio", "character": "Cobb", "photo_url": "https://image.tmdb.org/t/p/w185/wo2hJpn04vbtmh0B9utCFdsQhxM.jpg"}],
    "trailer_key": "YoHD9XEInc0"
  }
}
```

**Errors:** `404` `"Movie not found."` (unknown id, or not a UUID)

### GET /api/movies/popular

TMDB's popular movies, 20 per page, as movie cards with their **Catalog** ids. Every movie on the page is made sure to
exist in Catalog: a movie seen for the first time is created from the list data (title, overview, poster, release date,
`vote_average`, genres) without an extra TMDB call, and gets its full details (cast, runtime, trailer) when it is first
opened (`GET /api/movies/{id}`). A movie already in Catalog is used as it is (an admin's edits are kept).

The pages are TMDB's: `last_page` is at most 500 and `total` at most 10 000 (TMDB serves no more). Each TMDB page is
cached for **2 hours**, so a page opened again in that time does not call TMDB.

**Who:** guest

**Request**

| Field | Type | Rules |
|---|---|---|
| `page` | integer | query, optional, default 1, 1–500 |

**Response** `200`

```json
{
  "data": [
    {"id": "0193a7c1-3333-7aaa-8bbb-000000000100", "title": "Inception", "poster_url": "https://image.tmdb.org/t/p/w342/oYuLEt3zVCKq57qu2F8dT7NIa6f.jpg", "year": 2010, "release_date": "2010-07-15", "vote_average": 8.4, "genres": ["Action", "Science Fiction"]}
  ],
  "meta": {"current_page": 1, "last_page": 500, "total": 10000}
}
```

**Errors:** `422` `page` not an integer from 1 to 500 · `503` `"TMDB is unavailable."` (TMDB down and the page not
cached)

### GET /api/movies/search

Searches Catalog's movies by title, genre and release year, as movie cards with their **Catalog** ids. All filters work
together; without any filter it lists every movie.

With `q`, titles are matched case-insensitively anywhere in the title (`ILIKE`). When fewer than **10** local movies
match all the filters, TMDB's search is also asked once (its first page) and the movies missing from Catalog are
created from the result data, as `GET /api/movies/popular` does (no extra TMDB call per movie). If TMDB is down, the
local results are returned as they are.

**Who:** guest

**Request**

| Field | Type | Rules |
|---|---|---|
| `q` | string | query, optional, 2–100 characters; leave it out to not search by title |
| `genre` | uuid | query, optional, a Catalog genre id |
| `year` | integer | query, optional, release year |
| `sort` | string | query, optional: `popularity` (default), `vote_average` or `release_date`, always descending; movies without a value come last |
| `page` | integer | query, optional, default 1, 1–500; 20 movies per page |

`GET /api/movies/search?q=incep&genre=0193a7c2-…&year=2010&sort=vote_average`

**Response** `200`

```json
{
  "data": [
    {"id": "0193a7c1-3333-7aaa-8bbb-000000000100", "title": "Inception", "poster_url": "https://image.tmdb.org/t/p/w342/oYuLEt3zVCKq57qu2F8dT7NIa6f.jpg", "year": 2010, "release_date": "2010-07-15", "vote_average": 8.4, "genres": ["Action", "Science Fiction"]}
  ],
  "meta": {"current_page": 1, "last_page": 1, "total": 1}
}
```

**Errors:** `422` `q` shorter than 2 or longer than 100 characters, `genre` not a UUID, `year` not an integer, an
unknown `sort`, or `page` not an integer from 1 to 500

### GET /api/movies/upcoming

Like `GET /api/movies/popular`, for movies coming out soon in the configured region (`TMDB_REGION`, default `US`).
Only movies with a release date **after today (UTC)** are kept, so a page can hold fewer than 20 movies; `meta` stays
TMDB's.

**Who:** guest

**Request:** same as popular (`page`, 1–500)

**Response** `200`: same shape as popular

**Errors:** `422` invalid `page` · `503` `"TMDB is unavailable."` (TMDB down and the page not cached)

## Internal

For other services only: the `service` middleware refuses every call without the right `X-Service-Token`
(`docs/api/conventions.md`). Call them with `MoviePlatform\Common\Integration\InternalClient`.

### GET /api/internal/discover

The most popular **local** movies in some genres, as movie cards. TMDB is not called. Used by Intelligence's weather
suggestions.

**Who:** service (`X-Service-Token`)

**Request**

| Field | Type | Rules |
|---|---|---|
| `genres` | string | query, required, comma-separated genre names as in `GET /api/genres` (e.g. `Drama,Romance`), at most 10; a movie in any of them matches |
| `exclude_ids` | string | query, optional, comma-separated Catalog movie ids to leave out, at most 100 |
| `limit` | integer | query, optional, default 10, 1–50 |

`GET /api/internal/discover?genres=Comedy,Adventure&limit=10`

**Response** `200`, sorted by popularity, highest first; a movie in several of the genres appears once. Unknown genre
names match nothing.

```json
{
  "data": [
    {"id": "0193a7c1-3333-7aaa-8bbb-000000000100", "title": "Project Hail Mary", "poster_url": "https://image.tmdb.org/t/p/w342/abc.jpg", "year": 2026, "release_date": "2026-03-20", "vote_average": 7.9, "genres": ["Adventure", "Science Fiction"]}
  ]
}
```

**Errors:** `401` no or wrong service token · `422` `genres` missing, too many, an `exclude_ids` entry that is not a
UUID, or `limit` out of range

## Genres

### GET /api/genres

All genres, alphabetical, with their Catalog ids (the ids that `GET /api/movies/search?genre=`, Identity's
`genre_ids` and the endpoint below use). Not paginated: about 19 rows, filled by `catalog:sync-genres`.

**Who:** guest

**Response** `200`

```json
{"data": [{"id": "0193a7c1-4444-7aaa-8bbb-000000000001", "name": "Action"}, {"id": "0193a7c1-4444-7aaa-8bbb-000000000002", "name": "Adventure"}]}
```

### GET /api/genres/{id}/movies

The **local** movies of one genre (no TMDB call), as movie cards, 20 per page, sorted descending. `{id}` is a Catalog
genre id (UUID).

**Who:** guest

**Request**

| Field | Type | Rules |
|---|---|---|
| `sort` | string | query, optional, `popularity` (default) or `vote_average` |
| `page` | integer | query, optional, default 1, 1–500 |

**Response** `200`: movie cards, with `meta` as in `GET /api/movies/search`

```json
{
  "data": [
    {"id": "0193a7c1-3333-7aaa-8bbb-000000000100", "title": "Inception", "poster_url": "https://image.tmdb.org/t/p/w342/oYuLEt3zVCKq57qu2F8dT7NIa6f.jpg", "year": 2010, "release_date": "2010-07-15", "vote_average": 8.4, "genres": ["Action", "Science Fiction"]}
  ],
  "meta": {"current_page": 1, "last_page": 3, "total": 47}
}
```

**Errors:** `404` `"Genre not found."` (unknown id, or not a UUID) · `422` unknown `sort`, or `page` not an integer
from 1 to 500

## People

### GET /api/people/{id}

A person's page: details and filmography. `{id}` is the Catalog person id (UUID), as in a movie's `cast` and
`directors`.

A TMDB person is synced on first open, then at most once every **7 days** (the cache remembers it, since `people` has no
`synced_at`): their details and their movie credits as actor and director (TV credits and other crew jobs are left
out). A movie of the filmography seen for the first time is created from the credit data, like popular and upcoming
do, and gets its full details when it is first opened. If TMDB is down or no longer has the person, the local data is
served (the filmography then holds only the movies already synced) and the sync is tried again on the next open.

**Who:** guest

**Response** `200`

| Field | Type | Content |
|---|---|---|
| `id` | uuid | |
| `name` | string | |
| `photo_url` | string \| null | full TMDB image URL (w185) |
| `biography` | string \| null | |
| `birthday` | date (`YYYY-MM-DD`) \| null | |
| `known_for` | string \| null | TMDB department, e.g. `Acting`, `Directing` |
| `filmography.acting` | movie card[] | newest first, movies without a release date last |
| `filmography.directing` | movie card[] | same order |

```json
{
  "data": {
    "id": "0193a7c1-5555-7aaa-8bbb-000000000001",
    "name": "Christopher Nolan",
    "photo_url": "https://image.tmdb.org/t/p/w185/xuAIuYSmsUzKlUMBFGVZaWsY3DZ.jpg",
    "biography": "Christopher Edward Nolan is a British-American filmmaker…",
    "birthday": "1970-07-30",
    "known_for": "Directing",
    "filmography": {
      "acting": [],
      "directing": [
        {"id": "0193a7c1-3333-7aaa-8bbb-000000000100", "title": "Inception", "poster_url": "https://image.tmdb.org/t/p/w342/oYuLEt3zVCKq57qu2F8dT7NIa6f.jpg", "year": 2010, "release_date": "2010-07-15", "vote_average": 8.4, "genres": ["Action", "Science Fiction"]}
      ]
    }
  }
}
```

**Errors:** `404` `"Person not found."` (unknown id, or not a UUID)
