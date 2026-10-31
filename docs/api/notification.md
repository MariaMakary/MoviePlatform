# Notification API

Port **8006**. The current user's notifications. Follows `docs/api/conventions.md`.

Notifications are created only by this service's event handlers (`docs/events.md`), never through the API. Every
route works on the token's user only: another user's notification answers `404`, as if it did not exist.

## Notifications

A notification:

| Field | Type | Content |
|---|---|---|
| `id` | uuid | |
| `type` | string | kind of notification: `welcome`, `new_follower`, `movie_night_invite`, … |
| `title` | string | short text for the bell |
| `body` | string | full text |
| `data` | object \| null | ids the Gateway needs to build links (e.g. `follower_id`, `movie_night_id`) |
| `read_at` | date \| null | `null` while unread |
| `created_at` | date | |

### GET /api/notifications

Lists the current user's notifications, newest first.

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
    {"id": "0193a7d0-5555-7aaa-8bbb-000000000600", "type": "new_follower", "title": "New follower", "body": "Maria started following you.", "data": {"follower_id": "0193a7c0-2222-7aaa-8bbb-000000000002"}, "read_at": null, "created_at": "2026-11-03T18:20:00Z"}
  ],
  "meta": {"current_page": 1, "last_page": 1, "total": 1}
}
```

**Errors:** `401` no or invalid token

### GET /api/notifications/unread-count

Number of the current user's unread notifications, for the bell badge.

**Who:** user

**Response** `200`

```json
{"data": {"count": 3}}
```

**Errors:** `401` no or invalid token

### PATCH /api/notifications/{id}/read

Marks one of the current user's notifications as read. Marking an already read notification keeps its `read_at`.

**Who:** user

**Response** `200`, the notification

```json
{"data": {"id": "0193a7d0-5555-7aaa-8bbb-000000000600", "type": "new_follower", "title": "New follower", "body": "Maria started following you.", "data": {"follower_id": "0193a7c0-2222-7aaa-8bbb-000000000002"}, "read_at": "2026-11-03T18:25:00Z", "created_at": "2026-11-03T18:20:00Z"}}
```

**Errors:** `401` no or invalid token · `404` `"Notification not found."` (unknown id, or another user's notification)

### PATCH /api/notifications/read-all

Marks all of the current user's unread notifications as read. Already read ones keep their `read_at`.

**Who:** user

**Response** `204`, no body

**Errors:** `401` no or invalid token
