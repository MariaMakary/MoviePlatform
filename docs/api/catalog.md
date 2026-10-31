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
    {"id": "0193a7c1-3333-7aaa-8bbb-000000000101", "title": "Interstellar", "poster_url": "https://image.tmdb.org/t/p/w342/gEU2QniE6E77NI6lCU6MxlNBvIx.jpg", "year": 2014, "vote_average": 8.5, "genres": ["Adventure", "Drama", "Science Fiction"]},
    {"id": "0193a7c1-3333-7aaa-8bbb-000000000100", "title": "Inception", "poster_url": "https://image.tmdb.org/t/p/w342/oYuLEt3zVCKq57qu2F8dT7NIa6f.jpg", "year": 2010, "vote_average": 8.4, "genres": ["Action", "Science Fiction"]}
  ]
}
```

**Errors:** `422` `ids` missing or empty, more than 100 ids, or an id that is not a UUID (`errors.ids` or
`errors.ids.<position>`)
