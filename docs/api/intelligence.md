# Intelligence API

Port **8005**. Recommendations, review sentiment, weather and calendar. Follows `docs/api/conventions.md`.

## Calendar

"Add to calendar" exports of one event (a movie release, a movie night). Nothing is stored: both endpoints build the
export from the fields sent. They take the same fields:

| Field | Type | Rules |
|---|---|---|
| `title` | string | required, max 255 |
| `description` | string | optional, max 5000, may contain newlines |
| `start` | date | required. Timed event: ISO-8601 date-time (`2026-11-07T20:00:00Z`, any offset is converted to UTC). All-day event: a date (`2026-12-19`) |
| `end` | date | optional, ≥ `start`. Timed event: defaults to `start` + 2 hours. All-day event: the **last day** of the event (inclusive), defaults to `start` |
| `all_day` | boolean | optional, default `false`. In a query string: `1` or `0` |
| `location` | string | optional, max 255 |
| `url` | string | optional, a valid URL, max 2048 |

### POST /api/calendar/ics

Returns the event as an RFC 5545 `.ics` file. **The one non-JSON response of the platform**; validation errors are
still JSON.

**Who:** guest

**Request:** the fields above, as a JSON body.

**Response** `200`, `Content-Type: text/calendar; charset=utf-8`,
`Content-Disposition: attachment; filename="<slug-of-title>.ics"`

```text
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//MoviePlatform//Intelligence//EN
CALSCALE:GREGORIAN
METHOD:PUBLISH
BEGIN:VEVENT
UID:2b0e674b-0619-473e-8885-330a5c91864a@movieplatform
DTSTAMP:20261030T183405Z
DTSTART:20261107T180000Z
DTEND:20261107T200000Z
SUMMARY:Movie night: Dune\, Part Two
DESCRIPTION:Bring snacks\; seats A1\, A2\nSee you there!
LOCATION:Cinema City\, Beirut
URL:https://example.com/movies/1
END:VEVENT
END:VCALENDAR
```

- Lines end with CRLF and are folded at 75 octets (a continuation line starts with a space).
- `\`, `;`, `,` and newlines in `title`, `description` and `location` are escaped (`\\`, `\;`, `\,`, `\n`).
- All-day events use `DTSTART;VALUE=DATE:20261219` and an exclusive `DTEND;VALUE=DATE` (the day after the last day).
- `UID` is new on every call: importing the same event twice creates two events.

**Errors:** `422` invalid fields (JSON, see conventions)

### GET /api/calendar/google-link

Returns a Google Calendar "add event" URL that opens a pre-filled event. `url` is appended to the description, since
Google has no separate field for it.

**Who:** guest

**Request:** the fields above, as query parameters.

**Response** `200`

```json
{"data": {"url": "https://calendar.google.com/calendar/render?action=TEMPLATE&text=Release%3A%20Avatar%203&dates=20261219%2F20261220"}}
```

**Errors:** `422` invalid fields

## Recommendations

Built from the user's taste profile, which Intelligence keeps up to date from `RatingAdded`, `MovieWatched` and
`UserGenresUpdated` (`docs/events.md`). Candidates are Catalog's most popular movies in the user's top 3 genres (favorite
genres count +3) and movies similar to their 5 best-rated movies (rated 4 or 5); a movie the user has watched or rated is
never recommended. Score = (genre match × 3 + people match × 2 + similar 2 + `vote_average` × 0.5) ÷ 12, so 0–1. The best
20 are stored and recomputed when they are older than an hour.

### GET /api/recommendations/{userId}

A user's recommendations, best first. Recomputed first when the stored ones are older than an hour; if Catalog is
down while recomputing, the stored ones are returned as they are.

**Who:** user (their own `userId`), or admin (any)

**Response** `200`: an empty `data` for a user without a taste profile yet (no ratings, watches or favorite genres)

```json
{
  "data": [
    {"movie_id": "0193a7c1-3333-7aaa-8bbb-000000000101", "score": 0.8542, "reason": "Because you liked Inception"},
    {"movie_id": "0193a7c1-3333-7aaa-8bbb-000000000102", "score": 0.6125, "reason": "You like Science Fiction"}
  ]
}
```

`reason` is `Because you liked <title>` (similar to a movie they rated 4 or 5), `You like <genre>`, or
`Popular in your genres`. Get the movie cards with Catalog `GET /api/movies?ids=`.

**Errors:** `401` no or invalid token · `403` another user's recommendations · `404` `userId` is not a UUID

## Sentiment

Every review is analyzed in the background: Intelligence consumes `ReviewCreated` and `ReviewUpdated`, asks Azure AI
Language (the team's free resource, 5,000 texts a month), keeps one result per review in `sentiment_results` and
publishes `ReviewAnalyzed` (`docs/events.md`) after saving it. If Azure keeps failing, the event ends in
`intelligence.events.dlq` (`php artisan events:replay-dlq` after the fix); the review itself is already saved in
Engagement.

### POST /api/sentiment/analyze

Analyzes a text without storing anything, for manual testing. Admin only, so nobody else can spend the team's free
Azure texts.

**Who:** admin

**Request**

| Field | Type | Rules |
|---|---|---|
| `text` | string | required, max 5000 characters, not only spaces; texts over 1000 characters are cut before analysis |

**Response** `200`

```json
{"data": {"label": "positive", "score": 0.97}}
```

`label` is `positive`, `neutral` or `negative`; `score` (0–1) is Azure's confidence in it.

**Errors:** `401` no or invalid token · `403` not an admin · `422` invalid `text` · `503`
`"Azure AI Language is unavailable."` (timeout, bad key, free limit used up, refused text) or a message saying the key
or endpoint is not set up
