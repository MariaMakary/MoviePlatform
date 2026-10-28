# Event contracts

How services tell each other that something happened, through RabbitMQ. Publishers and consumers live in different
Laravel projects with no shared classes, so **this document is the contract**: publish exactly these fields, and read
exactly these fields.

## 1. RabbitMQ setup

- One **topic exchange**: `movieplatform.events` (durable).
- **Routing key** = the event name in dot form: `ReviewCreated` → `review.created`.
- Each **consuming** service has one durable queue, `<service>.events`, and a dead-letter queue,
  `<service>.events.dlq`. The service declares the exchange, both queues and its bindings **in code when its
  `events:consume` worker starts**, from its `config/events.php`. Declaring something that already exists does nothing.
- Only **engagement, social, intelligence and notification** consume events. Identity and Catalog only publish.

| Queue | Bound routing keys |
|---|---|
| `engagement.events` | `user.blocked`, `user.deleted`, `review.analyzed` |
| `social.events` | `user.blocked`, `user.deleted`, `rating.added`, `review.created`, `review.updated`, `movie.watched`, `list.created` |
| `intelligence.events` | `user.genres_updated`, `movie.updated`, `rating.added`, `review.created`, `review.updated`, `movie.watched` |
| `notification.events` | `user.registered`, `user.blocked`, `user.deleted`, `user.followed`, `movie_night.invite_sent`, `movie_night.invite_answered`, `movie_night.finalized` |

Dead letters: each `<service>.events` queue is declared with `x-dead-letter-exchange: ""` and
`x-dead-letter-routing-key: <service>.events.dlq`, so a rejected message moves to that service's DLQ.

## 2. Envelope

Every message has the same envelope. Only `data` changes per event.

```json
{
  "event": "ReviewCreated",
  "id": "0193a7d4-2c1b-7e5f-8a9b-0c1d2e3f4a5b",
  "occurred_at": "2026-11-03T18:20:00Z",
  "correlation_id": "0193a7d4-2b00-7a1c-9d8e-7f6a5b4c3d2e",
  "data": { }
}
```

| Field | Type | Content |
|---|---|---|
| `event` | string | The event name in PascalCase |
| `id` | uuid | Unique per message; consumers use it to skip duplicates |
| `occurred_at` | ISO-8601 UTC | When it happened |
| `correlation_id` | uuid | The `X-Correlation-ID` of the request that caused it (see `docs/api/conventions.md`) |
| `data` | object | The event's fields below |

Message properties: persistent (`delivery_mode` 2), `content_type: application/json`, `message_id` = the envelope `id`.

## 3. Rules

**Publishing**

- Publish **after the database transaction commits**, never inside it: an event must never describe data that was
  rolled back.
- Send the ids consumers need plus the few values they would otherwise have to ask for (e.g. a name for a
  notification text). Not whole records.
- All ids are **UUID strings**. `user_id` = Identity `users.id`, `movie_id` = Catalog `movies.id` (never `tmdb_id`).

**Consuming**

- Handlers are **idempotent**: an envelope `id` already in `processed_events` is acknowledged and skipped.
- On success: ack. On failure: retry up to 3 times, then reject without requeue, so the message goes to the DLQ.
  `php artisan events:replay-dlq` moves dead letters back after a fix.
- **Ignore fields you don't know.** That is what makes adding a field safe.
- The `events:consume` worker starts from the service's `composer run dev` script, next to its `php -S` server.
  Never as a Docker container.

**Changing a contract**

- **Adding** a field: allowed. Update this document in the same PR.
- **Renaming or removing** a field, or changing its type or meaning: tell every consumer listed for that event
  **first**, and change publisher and consumers together.
- New event: add it here first (name, routing key, fields, publisher, consumers), then code it.
- Each publisher reviews their own events in the PR that changes this file.

## 4. Events

| Event | Routing key | Publisher | Consumers |
|---|---|---|---|
| [UserRegistered](#userregistered) | `user.registered` | Identity | Notification |
| [UserGenresUpdated](#usergenresupdated) | `user.genres_updated` | Identity | Intelligence |
| [UserBlocked](#userblocked) | `user.blocked` | Identity | Engagement, Social, Notification |
| [UserDeleted](#userdeleted) | `user.deleted` | Identity | Engagement, Social, Notification |
| [MovieUpdated](#movieupdated) | `movie.updated` | Catalog | Intelligence |
| [RatingAdded](#ratingadded) | `rating.added` | Engagement | Intelligence, Social |
| [ReviewCreated](#reviewcreated) | `review.created` | Engagement | Intelligence, Social |
| [ReviewUpdated](#reviewupdated) | `review.updated` | Engagement | Intelligence, Social |
| [MovieWatched](#moviewatched) | `movie.watched` | Engagement | Intelligence, Social |
| [ListCreated](#listcreated) | `list.created` | Engagement | Social |
| [ReviewAnalyzed](#reviewanalyzed) | `review.analyzed` | Intelligence | Engagement |
| [UserFollowed](#userfollowed) | `user.followed` | Social | Notification |
| [MovieNightInviteSent](#movienightinvitesent) | `movie_night.invite_sent` | Social | Notification |
| [MovieNightInviteAnswered](#movienightinviteanswered) | `movie_night.invite_answered` | Social | Notification |
| [MovieNightFinalized](#movienightfinalized) | `movie_night.finalized` | Social | Notification |

### Identity

#### UserRegistered

`user.registered` · a new account was created (email or social login) · → Notification (welcome notification)

| Field | Type | Notes |
|---|---|---|
| `user_id` | uuid | |
| `name` | string | |
| `email` | string | |

```json
{"user_id": "0193a7c0-1111-7aaa-8bbb-000000000001", "name": "Maria Makary", "email": "maria@example.com"}
```

#### UserGenresUpdated

`user.genres_updated` · the user changed their favourite genres · → Intelligence (taste profile)

| Field | Type | Notes |
|---|---|---|
| `user_id` | uuid | |
| `genre_ids` | uuid[] | The **full** new list (Catalog `genres.id`), not a diff. Can be empty. |

```json
{"user_id": "0193a7c0-1111-7aaa-8bbb-000000000001", "genre_ids": ["0193a7b0-2222-7aaa-8bbb-000000000010", "0193a7b0-2222-7aaa-8bbb-000000000011"]}
```

#### UserBlocked

`user.blocked` · an admin blocked the user · → Engagement (hide their reviews), Social (hide their activity),
Notification (stop sending to them)

| Field | Type | Notes |
|---|---|---|
| `user_id` | uuid | |

```json
{"user_id": "0193a7c0-1111-7aaa-8bbb-000000000001"}
```

#### UserDeleted

`user.deleted` · the account was deleted · → Engagement, Social, Notification (delete or anonymise that user's data)

| Field | Type | Notes |
|---|---|---|
| `user_id` | uuid | |

```json
{"user_id": "0193a7c0-1111-7aaa-8bbb-000000000001"}
```

### Catalog

#### MovieUpdated

`movie.updated` · a movie was added to the local database or its data changed (e.g. TMDB sync) · → Intelligence
(recommendations)

| Field | Type | Notes |
|---|---|---|
| `movie_id` | uuid | Catalog `movies.id` |
| `title` | string | |
| `release_date` | date (`YYYY-MM-DD`) or null | |
| `genre_ids` | uuid[] | Catalog `genres.id` |
| `person_ids` | uuid[] | Catalog `people.id`: main cast and directors |

```json
{"movie_id": "0193a7c1-3333-7aaa-8bbb-000000000100", "title": "Inception", "release_date": "2010-07-16", "genre_ids": ["0193a7b0-2222-7aaa-8bbb-000000000010"], "person_ids": ["0193a7b5-4444-7aaa-8bbb-000000000200"]}
```

### Engagement

#### RatingAdded

`rating.added` · a user rated a movie, **or changed their rating** (one rating per user and movie) · → Intelligence,
Social

| Field | Type | Notes |
|---|---|---|
| `rating_id` | uuid | |
| `user_id` | uuid | |
| `movie_id` | uuid | |
| `score` | integer 1–5 | The current score |

```json
{"rating_id": "0193a7d0-5555-7aaa-8bbb-000000000300", "user_id": "0193a7c0-1111-7aaa-8bbb-000000000001", "movie_id": "0193a7c1-3333-7aaa-8bbb-000000000100", "score": 4}
```

#### ReviewCreated

`review.created` · a user wrote a review · → Intelligence (sentiment analysis), Social (activity feed)

| Field | Type | Notes |
|---|---|---|
| `review_id` | uuid | |
| `user_id` | uuid | |
| `movie_id` | uuid | |
| `body` | string | The review text, needed for sentiment analysis |

```json
{"review_id": "0193a7d1-6666-7aaa-8bbb-000000000400", "user_id": "0193a7c0-1111-7aaa-8bbb-000000000001", "movie_id": "0193a7c1-3333-7aaa-8bbb-000000000100", "body": "Loved the ending, the soundtrack is amazing."}
```

#### ReviewUpdated

`review.updated` · the author edited the review text · → Intelligence (analyse again), Social

Same fields as [ReviewCreated](#reviewcreated), with the new `body`.

#### MovieWatched

`movie.watched` · a movie was added to the user's watch history · → Intelligence, Social

| Field | Type | Notes |
|---|---|---|
| `user_id` | uuid | |
| `movie_id` | uuid | |
| `watched_at` | ISO-8601 UTC | When the user watched it (may be earlier than `occurred_at`) |

```json
{"user_id": "0193a7c0-1111-7aaa-8bbb-000000000001", "movie_id": "0193a7c1-3333-7aaa-8bbb-000000000100", "watched_at": "2026-11-02T21:00:00Z"}
```

#### ListCreated

`list.created` · a user created a custom list · → Social (activity feed, only if public)

| Field | Type | Notes |
|---|---|---|
| `list_id` | uuid | |
| `user_id` | uuid | |
| `title` | string | |
| `is_public` | boolean | Social ignores private lists |

```json
{"list_id": "0193a7d2-7777-7aaa-8bbb-000000000500", "user_id": "0193a7c0-1111-7aaa-8bbb-000000000001", "title": "Best of Nolan", "is_public": true}
```

### Intelligence

#### ReviewAnalyzed

`review.analyzed` · sentiment analysis of a review finished · → Engagement (stores it on the review)

| Field | Type | Notes |
|---|---|---|
| `review_id` | uuid | |
| `label` | `positive` \| `neutral` \| `negative` | Stored as `reviews.sentiment_label` |
| `score` | number 0–1 | Model confidence, stored as `reviews.sentiment_score` |

```json
{"review_id": "0193a7d1-6666-7aaa-8bbb-000000000400", "label": "positive", "score": 0.94}
```

### Social

#### UserFollowed

`user.followed` · a user started following another user · → Notification (tell the followed user)

| Field | Type | Notes |
|---|---|---|
| `follower_id` | uuid | Who follows |
| `follower_name` | string | For the notification text |
| `following_id` | uuid | Who is followed (receives the notification) |

```json
{"follower_id": "0193a7c0-1111-7aaa-8bbb-000000000002", "follower_name": "Joy Alahel", "following_id": "0193a7c0-1111-7aaa-8bbb-000000000001"}
```

#### MovieNightInviteSent

`movie_night.invite_sent` · the host invited a user to a movie night · → Notification (tell the invited user)

| Field | Type | Notes |
|---|---|---|
| `movie_night_id` | uuid | |
| `host_id` | uuid | |
| `host_name` | string | For the notification text |
| `user_id` | uuid | The invited user |
| `scheduled_at` | ISO-8601 UTC | |

```json
{"movie_night_id": "0193a7d3-8888-7aaa-8bbb-000000000600", "host_id": "0193a7c0-1111-7aaa-8bbb-000000000001", "host_name": "Maria Makary", "user_id": "0193a7c0-1111-7aaa-8bbb-000000000003", "scheduled_at": "2026-11-07T19:00:00Z"}
```

#### MovieNightInviteAnswered

`movie_night.invite_answered` · an invited user accepted or declined · → Notification (tell the host)

| Field | Type | Notes |
|---|---|---|
| `movie_night_id` | uuid | |
| `host_id` | uuid | Receives the notification |
| `user_id` | uuid | Who answered |
| `user_name` | string | For the notification text |
| `status` | `accepted` \| `declined` | |

```json
{"movie_night_id": "0193a7d3-8888-7aaa-8bbb-000000000600", "host_id": "0193a7c0-1111-7aaa-8bbb-000000000001", "user_id": "0193a7c0-1111-7aaa-8bbb-000000000003", "user_name": "Tonio Jreige", "status": "accepted"}
```

#### MovieNightFinalized

`movie_night.finalized` · voting closed and the movie is chosen · → Notification (tell every accepted participant)

| Field | Type | Notes |
|---|---|---|
| `movie_night_id` | uuid | |
| `host_id` | uuid | |
| `participant_ids` | uuid[] | Users who accepted, without the host |
| `winner_movie_id` | uuid | Catalog `movies.id` |
| `winner_movie_title` | string | For the notification text |
| `scheduled_at` | ISO-8601 UTC | |
| `location` | string or null | |
| `meeting_link` | string or null | |

```json
{"movie_night_id": "0193a7d3-8888-7aaa-8bbb-000000000600", "host_id": "0193a7c0-1111-7aaa-8bbb-000000000001", "participant_ids": ["0193a7c0-1111-7aaa-8bbb-000000000003"], "winner_movie_id": "0193a7c1-3333-7aaa-8bbb-000000000100", "winner_movie_title": "Inception", "scheduled_at": "2026-11-07T19:00:00Z", "location": null, "meeting_link": "https://meet.example.com/abc"}
```
