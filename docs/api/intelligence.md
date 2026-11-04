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

## Sentiment

Every review is analyzed in the background: Intelligence consumes `ReviewCreated` and `ReviewUpdated`, asks the
Hugging Face model `cardiffnlp/twitter-roberta-base-sentiment-latest`, keeps one result per review in
`sentiment_results` and publishes `ReviewAnalyzed` (`docs/events.md`) after saving it. If Hugging Face keeps failing,
the event ends in `intelligence.events.dlq` (`php artisan events:replay-dlq` after the fix); the review itself is
already saved in Engagement.

### POST /api/sentiment/analyze

Analyzes a text without storing anything, for manual testing. Admin only, so nobody else can spend the Hugging Face
token.

**Who:** admin

**Request**

| Field | Type | Rules |
|---|---|---|
| `text` | string | required, max 5000 characters, not only spaces; texts over 1500 characters are cut before analysis |

**Response** `200`

```json
{"data": {"label": "positive", "score": 0.9731}}
```

`label` is `positive`, `neutral` or `negative`; `score` (0–1) is the model's confidence in it.

**Errors:** `401` no or invalid token · `403` not an admin · `422` invalid `text` · `503` `"Hugging Face is unavailable."`
(timeout, rate limit, model loading, no credits or no token)
